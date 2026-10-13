<?php

namespace App\Http\Controllers\Compras;

use App\Enums\EstadoOrdenCompra;
use App\Enums\Permiso;
use App\Exceptions\CompraRechazada;
use App\Http\Controllers\Controller;
use App\Http\Requests\Compras\AnularCompraRequest;
use App\Http\Requests\Compras\GuardarOrdenCompraRequest;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use App\Models\Proveedor;
use App\Services\OrdenesCompra;
use App\Services\SucursalActiva;
use App\Services\VariantesCompra;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Órdenes de compra de la sucursal activa (COM-01). Las de otra sucursal responden 404.
 */
class OrdenCompraController extends Controller
{
    public function __construct(private SucursalActiva $sucursalActiva) {}

    public function index(Request $request): View
    {
        Gate::authorize(Permiso::ComprasVer->value);

        $filtros = $request->validate([
            'estado' => ['nullable', Rule::enum(EstadoOrdenCompra::class)],
            'proveedor_id' => ['nullable', 'integer'],
        ]);

        $ordenes = OrdenCompra::query()
            ->where('sucursal_id', $this->sucursalActiva->id())
            ->with('proveedor:id,razon_social')
            ->withSum('items as unidades_pedidas', 'cantidad_pedida')
            ->withSum('items as unidades_recibidas', 'cantidad_recibida')
            ->when($filtros['estado'] ?? null, fn (Builder $query, $estado) => $query->where('estado', $estado))
            ->when($filtros['proveedor_id'] ?? null, fn (Builder $query, $id) => $query->where('proveedor_id', $id))
            ->orderByDesc('numero')
            ->paginate(30)
            ->withQueryString();

        return view('compras.ordenes.index', [
            'ordenes' => $ordenes,
            'filtros' => $filtros,
            'proveedores' => Proveedor::query()->orderBy('razon_social')->get(['id', 'razon_social']),
            'puedeGestionar' => $request->user()->can(Permiso::ComprasGestionar->value),
        ]);
    }

    public function create(VariantesCompra $variantes): View
    {
        Gate::authorize(Permiso::ComprasGestionar->value);

        return view('compras.ordenes.form', [
            'orden' => null,
            'proveedores' => Proveedor::query()->activos()->orderBy('razon_social')->get(['id', 'razon_social']),
            'items' => $variantes->hidratar((array) old('items', [])),
        ]);
    }

    public function store(GuardarOrdenCompraRequest $request, OrdenesCompra $ordenes): RedirectResponse
    {
        $orden = $ordenes->crear(
            $this->sucursalActiva->sucursal(),
            Proveedor::findOrFail($request->validated('proveedor_id')),
            Carbon::parse($request->validated('fecha')),
            $request->observaciones(),
            $request->items(),
        );

        return redirect()->route('compras.ordenes.show', $orden)->with('status', "Orden N° {$orden->numero} creada en borrador.");
    }

    public function show(Request $request, OrdenCompra $orden): View
    {
        Gate::authorize(Permiso::ComprasVer->value);
        $this->exigirSucursalActiva($orden);

        $orden->load([
            'proveedor',
            'user:id,name',
            'items' => fn ($query) => $query->orderBy('id'),
            'items.variante:id,producto_id,color_id,talle,sku',
            'items.variante.producto:id,nombre',
            'items.variante.color:id,nombre',
            'recepciones' => fn ($query) => $query->withSum('items as unidades', 'cantidad')->orderByDesc('id'),
            'recepciones.user:id,name',
        ]);

        return view('compras.ordenes.show', [
            'orden' => $orden,
            'puedeGestionar' => $request->user()->can(Permiso::ComprasGestionar->value),
            'puedeRecibir' => $request->user()->can(Permiso::ComprasRecibir->value),
        ]);
    }

    public function edit(OrdenCompra $orden, VariantesCompra $variantes): View|RedirectResponse
    {
        Gate::authorize(Permiso::ComprasGestionar->value);
        $this->exigirSucursalActiva($orden);

        if (! $orden->estado->editable()) {
            return redirect()->route('compras.ordenes.show', $orden)
                ->withErrors(['orden' => 'Solo se edita una orden en borrador.']);
        }

        $orden->load(['items' => fn ($query) => $query->orderBy('id')]);
        $entrada = old('items', $orden->items->map(fn (OrdenCompraItem $item) => [
            'variante_id' => $item->variante_id,
            'cantidad' => $item->cantidad_pedida,
            'costo' => $item->costo,
        ])->all());

        return view('compras.ordenes.form', [
            'orden' => $orden,
            'proveedores' => Proveedor::query()->activosOIncluido($orden->proveedor_id)->orderBy('razon_social')->get(['id', 'razon_social']),
            'items' => $variantes->hidratar((array) $entrada),
        ]);
    }

    public function update(GuardarOrdenCompraRequest $request, OrdenCompra $orden, OrdenesCompra $ordenes): RedirectResponse
    {
        $this->exigirSucursalActiva($orden);

        $this->rechazoComoError(fn () => $ordenes->actualizar(
            $orden,
            Proveedor::findOrFail($request->validated('proveedor_id')),
            Carbon::parse($request->validated('fecha')),
            $request->observaciones(),
            $request->items(),
        ));

        return redirect()->route('compras.ordenes.show', $orden)->with('status', 'Cambios guardados.');
    }

    public function emitir(Request $request, OrdenCompra $orden, OrdenesCompra $ordenes): RedirectResponse
    {
        Gate::authorize(Permiso::ComprasGestionar->value);
        $this->exigirSucursalActiva($orden);

        $this->rechazoComoError(fn () => $ordenes->emitir($orden));

        return redirect()->route('compras.ordenes.show', $orden)->with('status', "Orden N° {$orden->numero} emitida.");
    }

    public function anular(AnularCompraRequest $request, OrdenCompra $orden, OrdenesCompra $ordenes): RedirectResponse
    {
        $this->exigirSucursalActiva($orden);

        $this->rechazoComoError(fn () => $ordenes->anular($orden, $request->motivo()), 'anulacion');

        return redirect()->route('compras.ordenes.show', $orden)->with('status', "Orden N° {$orden->numero} anulada.");
    }

    /**
     * Las órdenes de otra sucursal no existen para el usuario.
     */
    private function exigirSucursalActiva(OrdenCompra $orden): void
    {
        abort_unless((int) $orden->sucursal_id === (int) $this->sucursalActiva->id(), 404);
    }

    /**
     * @param  callable(): mixed  $operacion
     */
    private function rechazoComoError(callable $operacion, string $bolsa = 'default'): void
    {
        try {
            $operacion();
        } catch (CompraRechazada $e) {
            throw ValidationException::withMessages([$e->campo => $e->getMessage()])->errorBag($bolsa);
        }
    }
}
