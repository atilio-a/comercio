<?php

namespace App\Http\Controllers\Ventas;

use App\Enums\EstadoVenta;
use App\Enums\Parametro;
use App\Enums\Permiso;
use App\Enums\TipoMedioPago;
use App\Exceptions\CajaNoAbierta;
use App\Exceptions\CreditoNoDisponible;
use App\Exceptions\StockInsuficiente;
use App\Exceptions\VentaRechazada;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\AnularVentaRequest;
use App\Http\Requests\Ventas\PrevisualizarVentaRequest;
use App\Http\Requests\Ventas\RegistrarVentaRequest;
use App\Models\MedioPago;
use App\Models\PlanCuota;
use App\Models\PuntoVenta;
use App\Models\Venta;
use App\Services\CatalogoVenta;
use App\Services\Formato;
use App\Services\Importes;
use App\Services\MovimientosCaja;
use App\Services\Parametros;
use App\Services\SucursalActiva;
use App\Services\Vendedores;
use App\Services\Ventas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Ventas de la sucursal activa (VEN-01, VEN-02, VEN-05, VND-01): venta rápida, previsualización,
 * registro, listado, detalle y anulación. Las de otra sucursal responden 404 y, sin
 * `ventas.ver_todas`, el usuario solo ve las suyas.
 */
class VentaController extends Controller
{
    public function __construct(private SucursalActiva $sucursalActiva) {}

    public function index(Request $request, Vendedores $vendedores): View
    {
        $user = $request->user();
        abort_unless($user->canAny([Permiso::VentasVender->value, Permiso::VentasVerTodas->value]), 403);

        $verTodas = $user->can(Permiso::VentasVerTodas->value);
        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'vendedor_id' => ['nullable', 'integer'],
            'estado' => ['nullable', Rule::enum(EstadoVenta::class)],
        ]);

        $ventas = Venta::query()
            ->where('sucursal_id', $this->sucursalActiva->id())
            ->visiblesPara($user)
            ->with(['cliente:id,nombre', 'vendedor.user:id,name', 'comprobante'])
            ->when($filtros['desde'] ?? null, fn (Builder $query, string $desde) => $query->where('fecha', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn (Builder $query, string $hasta) => $query->where('fecha', '<=', $hasta))
            ->when($verTodas ? ($filtros['vendedor_id'] ?? null) : null, fn (Builder $query, $id) => $query->where('vendedor_id', $id))
            ->when($filtros['estado'] ?? null, fn (Builder $query, string $estado) => $query->where('estado', $estado))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('ventas.index', [
            'ventas' => $ventas,
            'filtros' => $filtros,
            'verTodas' => $verTodas,
            'vendedores' => $verTodas ? $vendedores->disponibles() : collect(),
            'puedeVender' => $user->can(Permiso::VentasVender->value),
        ]);
    }

    public function create(Request $request, Vendedores $vendedores, Parametros $parametros, MovimientosCaja $caja): View
    {
        Gate::authorize(Permiso::VentasVender->value);

        $disponibles = $vendedores->disponibles();
        $cajaAbierta = $caja->sesionAbierta() !== null;
        $propio = $disponibles->firstWhere('user_id', $request->user()->id);

        return view('ventas.create', [
            'configuracion' => [
                'usarTalles' => $parametros->habilitado(Parametro::UsarTalles),
                'vendedorId' => $propio?->id,
                'puedeAutorizar' => $request->user()->can(Permiso::VentasAutorizarDescuentos->value),
                'cajaAbierta' => $cajaAbierta,
                'medios' => $this->medios(),
                'urls' => [
                    'buscar' => route('ventas.variantes.buscar'),
                    'previsualizar' => route('ventas.previsualizar'),
                    'registrar' => route('ventas.store'),
                    'clientes' => route('clientes.buscar'),
                    'clienteRapido' => route('clientes.rapido'),
                ],
            ],
            'vendedores' => $disponibles,
            'puntosVenta' => PuntoVenta::query()->activos()->where('sucursal_id', $this->sucursalActiva->id())->orderBy('numero')->get(),
            'puedeCrearClientes' => $request->user()->can(Permiso::ClientesGestionar->value),
            'cajaAbierta' => $cajaAbierta,
        ]);
    }

    /**
     * Búsqueda de productos para la venta rápida (JSON).
     */
    public function buscarVariantes(Request $request, CatalogoVenta $catalogo): JsonResponse
    {
        Gate::authorize(Permiso::VentasVender->value);

        $filtros = $request->validate([
            'q' => ['required', 'string', 'max:150'],
            'exacto' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'productos' => $catalogo->buscar($filtros['q'], $request->boolean('exacto'), $this->sucursalActiva->id()),
        ]);
    }

    /**
     * Totales del carrito calculados por el servidor (JSON), sin grabar nada.
     */
    public function previsualizar(PrevisualizarVentaRequest $request, Ventas $ventas): JsonResponse
    {
        try {
            $calculo = $ventas->previsualizar($request->items(), $request->descuentoGeneral(), $request->pagos());
        } catch (VentaRechazada $e) {
            throw ValidationException::withMessages([$e->campo => $e->getMessage()]);
        }

        return response()->json([
            ...$calculo,
            'requiere_autorizacion' => $request->tieneDescuentos() && ! $request->user()->can(Permiso::VentasAutorizarDescuentos->value),
        ]);
    }

    public function store(RegistrarVentaRequest $request, Ventas $ventas): JsonResponse|RedirectResponse
    {
        try {
            $venta = $ventas->registrar(
                $this->sucursalActiva->sucursal(),
                $request->puntoVenta(),
                $request->vendedor(),
                $request->cliente(),
                $request->items(),
                $request->descuentoGeneral(),
                $request->pagos(),
                $request->autorizador(),
            );
        } catch (VentaRechazada $e) {
            throw ValidationException::withMessages([$e->campo => $e->getMessage()]);
        } catch (StockInsuficiente $e) {
            $posicion = collect($request->items())->search(fn (array $item) => $item['variante_id'] === $e->variante->id);

            throw ValidationException::withMessages(["items.{$posicion}.cantidad" => $e->getMessage()]);
        } catch (CajaNoAbierta|CreditoNoDisponible $e) {
            throw ValidationException::withMessages(['pagos' => $e->getMessage()]);
        }

        $mensaje = 'Venta registrada por $ '.Formato::importe($venta->total).'.';

        if ($request->wantsJson()) {
            return response()->json([
                'venta_id' => $venta->id,
                'mensaje' => $mensaje,
                'url' => route('ventas.show', $venta),
            ], 201);
        }

        return redirect()->route('ventas.show', $venta)->with('status', $mensaje);
    }

    public function show(Request $request, Venta $venta): View
    {
        $this->exigirSucursalActiva($venta);
        Gate::authorize('view', $venta);

        $venta->load([
            'cliente', 'vendedor.user:id,name', 'user:id,name', 'puntoVenta', 'anuladaPor:id,name',
            'autorizanteDescuento:id,name', 'comprobante', 'comprobanteAnulacion',
            'items' => fn ($query) => $query->orderBy('id'),
            'items.variante:id,producto_id,color_id,talle,sku',
            'items.variante.producto:id,nombre',
            'items.variante.color:id,nombre',
            'items.autorizanteDescuento:id,name',
            'pagos' => fn ($query) => $query->orderBy('id'),
            'pagos.medioPago:id,nombre',
        ]);

        return view('ventas.show', [
            'venta' => $venta,
            'puedeAnular' => $request->user()->can('anular', $venta),
            'descuentoGeneral' => Importes::aDecimal($venta->items->sum(fn ($item) => Importes::aCentavos((string) $item->descuento_venta))),
        ]);
    }

    public function anular(AnularVentaRequest $request, Venta $venta, Ventas $ventas): RedirectResponse
    {
        $this->exigirSucursalActiva($venta);

        try {
            $ventas->anular($venta, $request->motivo());
        } catch (VentaRechazada $e) {
            throw ValidationException::withMessages(['motivo' => $e->getMessage()])->errorBag('anulacion');
        } catch (StockInsuficiente|CajaNoAbierta|CreditoNoDisponible $e) {
            throw ValidationException::withMessages(['motivo' => $e->getMessage()])->errorBag('anulacion');
        }

        return redirect()->route('ventas.show', $venta)->with('status', "Venta N° {$venta->id} anulada.");
    }

    /**
     * Medios activos con sus planes activos, para la pantalla de venta.
     *
     * @return list<array<string, mixed>>
     */
    private function medios(): array
    {
        $ventas = app(Ventas::class);

        return MedioPago::query()
            ->activos()
            ->with(['planes' => fn ($query) => $query->where('activo', true)->orderBy('cuotas')])
            ->orderBy('id')
            ->get()
            ->map(fn (MedioPago $medio) => [
                'id' => $medio->id,
                'nombre' => $medio->nombre,
                'tipo' => $medio->tipo->value,
                'requiere_referencia' => $medio->requiere_referencia,
                'pasa_por_caja' => $ventas->pasaPorCaja($medio),
                'requiere_cliente' => ! $ventas->pasaPorCaja($medio),
                'admite_lote' => $medio->tipo->admitePlanes() || $medio->tipo === TipoMedioPago::TarjetaDebito,
                'planes' => $medio->planes->map(fn (PlanCuota $plan) => [
                    'id' => $plan->id,
                    'cuotas' => $plan->cuotas,
                    'recargo_pct' => (string) $plan->recargo_pct,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Las ventas de otra sucursal no existen para el usuario.
     */
    private function exigirSucursalActiva(Venta $venta): void
    {
        abort_unless((int) $venta->sucursal_id === (int) $this->sucursalActiva->id(), 404);
    }
}
