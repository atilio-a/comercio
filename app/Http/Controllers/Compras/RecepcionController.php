<?php

namespace App\Http\Controllers\Compras;

use App\Enums\Permiso;
use App\Exceptions\CompraRechazada;
use App\Exceptions\StockInsuficiente;
use App\Http\Controllers\Controller;
use App\Http\Requests\Compras\AnularCompraRequest;
use App\Http\Requests\Compras\RegistrarRecepcionRequest;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use App\Models\Proveedor;
use App\Models\Recepcion;
use App\Services\RecepcionesCompra;
use App\Services\SucursalActiva;
use App\Services\VariantesCompra;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Recepciones de mercadería de la sucursal activa (COM-02): desde una orden o libres,
 * detalle y anulación. Las de otra sucursal responden 404.
 */
class RecepcionController extends Controller
{
    public function __construct(private SucursalActiva $sucursalActiva) {}

    public function index(Request $request): View
    {
        Gate::authorize(Permiso::ComprasVer->value);

        $filtros = $request->validate([
            'proveedor_id' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $recepciones = Recepcion::query()
            ->where('sucursal_id', $this->sucursalActiva->id())
            ->with(['proveedor:id,razon_social', 'orden:id,numero', 'user:id,name'])
            ->withSum('items as unidades', 'cantidad')
            ->when($filtros['proveedor_id'] ?? null, fn (Builder $query, $id) => $query->where('proveedor_id', $id))
            ->when($filtros['desde'] ?? null, fn (Builder $query, $desde) => $query->where('fecha', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn (Builder $query, $hasta) => $query->where('fecha', '<=', $hasta))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('compras.recepciones.index', [
            'recepciones' => $recepciones,
            'filtros' => $filtros,
            'proveedores' => Proveedor::query()->orderBy('razon_social')->get(['id', 'razon_social']),
            'puedeRecibir' => $request->user()->can(Permiso::ComprasRecibir->value),
        ]);
    }

    public function create(Request $request, VariantesCompra $variantes): View|RedirectResponse
    {
        Gate::authorize(Permiso::ComprasRecibir->value);

        $orden = $request->filled('orden') ? $this->ordenDeLaSucursal($request->integer('orden')) : null;

        if ($orden && ! $orden->estado->recibible()) {
            return redirect()->route('compras.ordenes.show', $orden)
                ->withErrors(['orden' => "La orden N° {$orden->numero} no admite recepciones."]);
        }

        $items = $orden ? $this->itemsDeOrden($orden, $variantes) : $variantes->hidratar((array) old('items', []));

        return view('compras.recepciones.create', [
            'orden' => $orden,
            'proveedores' => $orden ? collect() : Proveedor::query()->activos()->orderBy('razon_social')->get(['id', 'razon_social']),
            'items' => $items,
        ]);
    }

    public function store(RegistrarRecepcionRequest $request, RecepcionesCompra $recepciones): RedirectResponse
    {
        $orden = $request->filled('orden_compra_id') ? $this->ordenDeLaSucursal((int) $request->validated('orden_compra_id')) : null;

        try {
            $recepcion = $recepciones->registrar(
                $this->sucursalActiva->sucursal(),
                $orden?->proveedor ?? Proveedor::findOrFail($request->validated('proveedor_id')),
                $orden,
                Carbon::parse($request->validated('fecha')),
                $request->texto('referencia'),
                $request->texto('observaciones'),
                $request->items(),
            );
        } catch (CompraRechazada $e) {
            throw ValidationException::withMessages([$e->campo => $e->getMessage()]);
        }

        return redirect()->route('compras.recepciones.show', $recepcion)->with('status', "Recepción N° {$recepcion->id} registrada.");
    }

    public function show(Request $request, Recepcion $recepcion): View
    {
        Gate::authorize(Permiso::ComprasVer->value);
        $this->exigirSucursalActiva($recepcion);

        $recepcion->load([
            'proveedor',
            'orden:id,numero,estado',
            'user:id,name',
            'anuladaPor:id,name',
            'items' => fn ($query) => $query->orderBy('variante_id'),
            'items.variante:id,producto_id,color_id,talle,sku',
            'items.variante.producto:id,nombre',
            'items.variante.color:id,nombre',
        ]);

        return view('compras.recepciones.show', [
            'recepcion' => $recepcion,
            'puedeAnular' => ! $recepcion->estaAnulada() && $request->user()->can(Permiso::ComprasGestionar->value),
        ]);
    }

    public function anular(AnularCompraRequest $request, Recepcion $recepcion, RecepcionesCompra $recepciones): RedirectResponse
    {
        $this->exigirSucursalActiva($recepcion);

        try {
            $recepciones->anular($recepcion, $request->motivo());
        } catch (CompraRechazada $e) {
            throw ValidationException::withMessages([$e->campo => $e->getMessage()])->errorBag('anulacion');
        } catch (StockInsuficiente $e) {
            throw ValidationException::withMessages([
                'motivo' => $e->getMessage().' No se puede anular la recepción sin dejar el stock en negativo.',
            ])->errorBag('anulacion');
        }

        return redirect()->route('compras.recepciones.show', $recepcion)->with('status', "Recepción N° {$recepcion->id} anulada.");
    }

    /**
     * Ítems de la orden con lo pendiente, para recibir; la cantidad empieza en 0.
     *
     * @return list<array<string, mixed>>
     */
    private function itemsDeOrden(OrdenCompra $orden, VariantesCompra $variantes): array
    {
        $orden->load([
            'items' => fn ($query) => $query->orderBy('id'),
            'items.variante.producto:id,nombre',
            'items.variante.color:id,nombre',
        ]);
        $anteriores = collect((array) old('items', []))->keyBy('variante_id');

        return $orden->items
            ->filter(fn (OrdenCompraItem $item) => $item->pendiente() > 0)
            ->map(function (OrdenCompraItem $item) use ($variantes, $anteriores) {
                $anterior = (array) $anteriores->get($item->variante_id, []);

                return [
                    ...$variantes->datos($item->variante),
                    'pendiente' => $item->pendiente(),
                    'cantidad' => (string) ($anterior['cantidad'] ?? '0'),
                    'costo' => (string) ($anterior['costo'] ?? $item->costo),
                    'actualizar_costo' => filter_var($anterior['actualizar_costo'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            })
            ->values()
            ->all();
    }

    private function ordenDeLaSucursal(int $id): OrdenCompra
    {
        return OrdenCompra::query()
            ->where('sucursal_id', $this->sucursalActiva->id())
            ->with('proveedor')
            ->findOrFail($id);
    }

    /**
     * Las recepciones de otra sucursal no existen para el usuario.
     */
    private function exigirSucursalActiva(Recepcion $recepcion): void
    {
        abort_unless((int) $recepcion->sucursal_id === (int) $this->sucursalActiva->id(), 404);
    }
}
