<?php

namespace App\Http\Controllers\Caja;

use App\Enums\EstadoSesionCaja;
use App\Enums\Permiso;
use App\Exceptions\CajaNoAbierta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Caja\ContraasientoRequest;
use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Services\MovimientosCaja;
use App\Services\SesionesCaja;
use App\Services\SucursalActiva;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Sesiones de caja de la sucursal activa: listado para quien supervisa, detalle con
 * movimientos y arqueo, y contraasiento de movimientos (CAJ-02, CAJ-05).
 */
class SesionCajaController extends Controller
{
    public function __construct(private SucursalActiva $sucursalActiva) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CajaSesion::class);

        $filtros = $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'estado' => ['nullable', Rule::enum(EstadoSesionCaja::class)],
        ]);

        $sesiones = CajaSesion::query()
            ->where('sucursal_id', $this->sucursalActiva->id())
            ->with(['caja:id,nombre', 'user:id,name'])
            ->withSum('arqueos as diferencia_total', 'diferencia')
            ->when($filtros['fecha'] ?? null, fn (Builder $query, $fecha) => $query->whereDate('fecha', $fecha))
            ->when($filtros['estado'] ?? null, fn (Builder $query, $estado) => $query->where('estado', $estado))
            ->orderByDesc('apertura')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('caja.sesiones.index', [
            'sesiones' => $sesiones,
            'filtros' => $filtros,
        ]);
    }

    public function show(Request $request, CajaSesion $sesion, SesionesCaja $sesiones): View
    {
        $this->exigirSucursalActiva($sesion->sucursal_id);
        Gate::authorize('view', $sesion);

        $sesion->load(['caja:id,nombre', 'user:id,name', 'arqueos.medioPago:id,nombre']);

        return view('caja.sesiones.show', [
            'sesion' => $sesion,
            'esperado' => $sesion->estaAbierta() ? $sesiones->arqueoPrevisto($sesion) : null,
            'movimientos' => $sesion->movimientos()
                ->with(['medioPago:id,nombre', 'user:id,name', 'contraasiento:id,reversa_de_id'])
                ->orderByDesc('id')
                ->paginate(50),
            'puedeContraasentar' => $sesion->estaAbierta() && $request->user()->can(Permiso::CajaSupervisar->value),
        ]);
    }

    public function contraasentar(ContraasientoRequest $request, CajaMovimiento $movimiento, MovimientosCaja $movimientos): RedirectResponse
    {
        $this->exigirSucursalActiva($movimiento->sucursal_id);

        if (! $movimiento->tipo->esManual()) {
            throw ValidationException::withMessages(['motivo' => 'Este movimiento se corrige desde la operación que lo originó.'])
                ->errorBag('contraasiento');
        }

        try {
            DB::transaction(fn () => $movimientos->contraasentar($movimiento, $request->validated('motivo')));
        } catch (CajaNoAbierta|InvalidArgumentException $e) {
            throw ValidationException::withMessages(['motivo' => $e->getMessage()])->errorBag('contraasiento');
        }

        return redirect()->route('caja.sesiones.show', $movimiento->caja_sesion_id)->with('status', 'Contraasiento registrado.');
    }

    /**
     * Las sesiones y movimientos de otra sucursal no existen para el usuario.
     */
    private function exigirSucursalActiva(int|string $sucursalId): void
    {
        abort_unless((int) $sucursalId === (int) $this->sucursalActiva->id(), 404);
    }
}
