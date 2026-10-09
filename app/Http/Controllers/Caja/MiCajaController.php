<?php

namespace App\Http\Controllers\Caja;

use App\Exceptions\CajaNoAbierta;
use App\Exceptions\CajaYaAbierta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Caja\AbrirCajaRequest;
use App\Http\Requests\Caja\CerrarCajaRequest;
use App\Http\Requests\Caja\RegistrarMovimientoCajaRequest;
use App\Models\Caja;
use App\Models\CajaSesion;
use App\Models\MedioPago;
use App\Services\MovimientosCaja;
use App\Services\SesionesCaja;
use App\Services\SucursalActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * "Mi caja": apertura, ingresos y egresos varios, y cierre con arqueo de la caja
 * propia en la sucursal activa (CAJ-02, CAJ-03).
 */
class MiCajaController extends Controller
{
    public function __construct(
        private MovimientosCaja $movimientos,
        private SesionesCaja $sesiones,
        private SucursalActiva $sucursalActiva,
    ) {}

    public function show(Request $request): View
    {
        $sesion = $this->movimientos->sesionAbierta();
        $sucursalId = $this->sucursalActiva->id();

        $anteriores = CajaSesion::query()
            ->where('sucursal_id', $sucursalId)
            ->where('user_id', $request->user()->id)
            ->where('estado', 'cerrada')
            ->with('caja:id,nombre')
            ->latest('apertura')
            ->limit(5)
            ->get();

        if (! $sesion) {
            $cajas = Caja::query()
                ->activasDe($sucursalId)
                ->with(['sesionAbierta.user:id,name'])
                ->orderBy('nombre')
                ->get();

            return view('caja.mi-caja', [
                'sesion' => null,
                'cajas' => $cajas,
                'anteriores' => $anteriores,
            ]);
        }

        return view('caja.mi-caja', [
            'sesion' => $sesion->load('caja:id,nombre'),
            'esperado' => $this->sesiones->arqueoPrevisto($sesion),
            'movimientos' => $sesion->movimientos()
                ->with(['medioPago:id,nombre', 'contraasiento:id,reversa_de_id'])
                ->orderByDesc('id')
                ->paginate(30),
            'medios' => MedioPago::query()->paraMovimientoManual()->orderBy('id')->get(),
            'anteriores' => $anteriores,
        ]);
    }

    public function abrir(AbrirCajaRequest $request): RedirectResponse
    {
        $caja = Caja::findOrFail($request->validated('caja_id'));

        try {
            $this->sesiones->abrir($caja, $request->fondo());
        } catch (CajaYaAbierta|InvalidArgumentException $e) {
            throw ValidationException::withMessages(['caja_id' => $e->getMessage()]);
        }

        return redirect()->route('caja.mi-caja')->with('status', "Abriste {$caja->nombre}.");
    }

    public function registrarMovimiento(RegistrarMovimientoCajaRequest $request): RedirectResponse
    {
        $tipo = $request->tipo();

        try {
            DB::transaction(fn () => $this->movimientos->registrar(
                $tipo,
                $request->medio(),
                $request->importeConSigno(),
                $request->validated('motivo'),
                referencia: $request->validated('referencia'),
            ));
        } catch (CajaNoAbierta|InvalidArgumentException $e) {
            throw ValidationException::withMessages(['importe' => $e->getMessage()]);
        }

        return redirect()->route('caja.mi-caja')->with('status', "{$tipo->etiqueta()} registrado.");
    }

    public function editCierre(): View|RedirectResponse
    {
        $sesion = $this->movimientos->sesionAbierta();

        if (! $sesion) {
            return redirect()->route('caja.mi-caja')->with('status', 'No tenés una caja abierta.');
        }

        return view('caja.cierre', [
            'sesion' => $sesion->load('caja:id,nombre'),
            'arqueo' => $this->sesiones->arqueoPrevisto($sesion),
        ]);
    }

    public function cerrar(CerrarCajaRequest $request): RedirectResponse
    {
        $sesion = $request->sesion();

        if (! $sesion) {
            return redirect()->route('caja.mi-caja')->with('status', 'No tenés una caja abierta.');
        }

        try {
            $this->sesiones->cerrar($sesion, $request->contados(), $request->validated('justificacion'));
        } catch (CajaNoAbierta|InvalidArgumentException $e) {
            throw ValidationException::withMessages(['justificacion' => $e->getMessage()]);
        }

        return redirect()->route('caja.sesiones.show', $sesion)->with('status', 'Caja cerrada.');
    }
}
