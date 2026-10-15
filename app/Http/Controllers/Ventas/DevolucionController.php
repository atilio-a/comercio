<?php

namespace App\Http\Controllers\Ventas;

use App\Enums\Permiso;
use App\Enums\ResolucionDevolucion;
use App\Enums\TipoMedioPago;
use App\Exceptions\CajaNoAbierta;
use App\Exceptions\DevolucionRechazada;
use App\Exceptions\VentaRechazada;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\RegistrarDevolucionRequest;
use App\Models\Devolucion;
use App\Models\MedioPago;
use App\Models\Venta;
use App\Services\Devoluciones;
use App\Services\MovimientosCaja;
use App\Services\SucursalActiva;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Devoluciones y cambios de ventas confirmadas (VEN-03, doc 05).
 *
 * Listado de la sucursal activa, formulario de devolución con reingreso de stock y nota de
 * crédito, y detalle de la devolución con acceso a la nota de crédito y al cambio.
 */
class DevolucionController extends Controller
{
    public function __construct(
        private SucursalActiva $sucursalActiva,
        private Devoluciones $devoluciones,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->can(Permiso::VentasDevolver->value), 403);

        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $devoluciones = Devolucion::query()
            ->where('sucursal_id', $this->sucursalActiva->id())
            ->whereHas('venta', fn (Builder $query) => $query->visiblesPara($user))
            ->with(['venta:id,sucursal_id,cliente_id', 'cliente:id,nombre', 'user:id,name', 'notaCredito'])
            ->when($filtros['desde'] ?? null, fn (Builder $query, string $desde) => $query->where('fecha', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn (Builder $query, string $hasta) => $query->where('fecha', '<=', $hasta))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('ventas.devoluciones.index', [
            'devoluciones' => $devoluciones,
            'filtros' => $filtros,
        ]);
    }

    public function create(Request $request, Venta $venta, MovimientosCaja $caja): View
    {
        abort_unless((int) $venta->sucursal_id === (int) $this->sucursalActiva->id(), 404);
        Gate::authorize('devolver', $venta);

        $venta->load([
            'cliente',
            'vendedor.user:id,name',
            'comprobante',
            'pagos.medioPago',
            'items.variante.producto:id,nombre',
            'items.variante.color:id,nombre',
            'items.devolucionItems',
        ]);

        $cantidadesDevueltas = $this->devoluciones->cantidadesDevueltas($venta);

        $cobradaPorCuenta = $venta->pagos->contains(fn ($pago) => ! $pago->medioPago->tipo->admiteMovimientoManual());
        $cajaAbierta = $caja->sesionAbierta() !== null;
        $puedeReembolsar = $request->user()->can(Permiso::VentasReembolsar->value);
        $admiteReembolso = ! $cobradaPorCuenta;

        $mediosReembolso = MedioPago::query()
            ->activos()
            ->whereNotIn('tipo', [TipoMedioPago::CuentaCorriente->value, TipoMedioPago::SaldoAFavor->value])
            ->orderBy('id')
            ->get();

        $itemsConfig = $venta->items->map(function ($item) use ($cantidadesDevueltas) {
            $devueltas = $cantidadesDevueltas[$item->id] ?? 0;

            return [
                'id' => $item->id,
                'producto' => $item->variante->producto->nombre,
                'descripcion' => $item->variante->descripcion(),
                'sku' => $item->variante->sku,
                'precio_final' => (string) $item->precio_final,
                'cantidad_vendida' => $item->cantidad,
                'cantidad_devuelta' => $devueltas,
                'disponibles' => max(0, $item->cantidad - $devueltas),
            ];
        })->values()->all();

        return view('ventas.devoluciones.create', [
            'venta' => $venta,
            'cantidadesDevueltas' => $cantidadesDevueltas,
            'resoluciones' => ResolucionDevolucion::cases(),
            'mediosReembolso' => $mediosReembolso,
            'cajaAbierta' => $cajaAbierta,
            'puedeReembolsar' => $puedeReembolsar,
            'admiteReembolso' => $admiteReembolso,
            'puedeCrearClientes' => $request->user()->can(Permiso::ClientesGestionar->value),
            'configuracion' => [
                'resolucion' => ResolucionDevolucion::SaldoAFavor->value,
                'ventaEsConsumidorFinal' => $venta->cliente_id === null,
                'cliente' => $venta->cliente ? [
                    'id' => $venta->cliente->id,
                    'nombre' => $venta->cliente->nombre,
                    'documento' => $venta->cliente->documentoFormateado(),
                ] : null,
                'puedeReembolsar' => $puedeReembolsar,
                'admiteReembolso' => $admiteReembolso,
                'cajaAbierta' => $cajaAbierta,
                'urls' => [
                    'clientes' => route('clientes.buscar'),
                    'clienteRapido' => route('clientes.rapido'),
                ],
                'items' => $itemsConfig,
            ],
        ]);
    }

    public function store(RegistrarDevolucionRequest $request, Venta $venta): JsonResponse|RedirectResponse
    {
        try {
            $devolucion = $this->devoluciones->registrar(
                $venta,
                $request->cantidades(),
                $request->motivo(),
                $request->resolucion(),
                $request->cliente(),
                $request->medioReembolso(),
                $request->referencia(),
            );
        } catch (DevolucionRechazada $e) {
            throw ValidationException::withMessages([$e->campo => $e->getMessage()]);
        } catch (CajaNoAbierta $e) {
            throw ValidationException::withMessages(['resolucion' => $e->getMessage()]);
        } catch (VentaRechazada $e) {
            throw ValidationException::withMessages(['motivo' => $e->getMessage()]);
        }

        $mensaje = "Devolución N° {$devolucion->id} registrada.";

        if ($request->wantsJson()) {
            return response()->json([
                'devolucion_id' => $devolucion->id,
                'mensaje' => $mensaje,
                'url' => route('ventas.devoluciones.show', $devolucion),
            ], 201);
        }

        return redirect()->route('ventas.devoluciones.show', $devolucion)->with('status', $mensaje);
    }

    public function show(Request $request, Devolucion $devolucion): View
    {
        abort_unless((int) $devolucion->sucursal_id === (int) $this->sucursalActiva->id(), 404);
        abort_unless($request->user()->can(Permiso::VentasDevolver->value), 403);
        Gate::authorize('view', $devolucion->venta);

        $devolucion->load([
            'venta.comprobante',
            'cliente.cuentaCorriente',
            'medioPago',
            'user:id,name',
            'notaCredito',
            'items.variante.producto:id,nombre',
            'items.variante.color:id,nombre',
            'items.ventaItem',
        ]);

        return view('ventas.devoluciones.show', [
            'devolucion' => $devolucion,
            'puedeVender' => $request->user()->can(Permiso::VentasVender->value),
        ]);
    }
}
