<?php

namespace App\Http\Controllers\Caja;

use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Caja\GuardarPlanCuotaRequest;
use App\Models\MedioPago;
use App\Models\PlanCuota;
use App\Services\RegistradorAuditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Planes de cuotas de un medio de tarjeta de crédito (CAJ-01). Solo con `caja.configurar`.
 */
class PlanCuotaController extends Controller
{
    public function create(MedioPago $medio): View
    {
        Gate::authorize(Permiso::CajaConfigurar->value);
        $this->exigirTarjetaDeCredito($medio);

        return view('caja.planes.form', ['medio' => $medio, 'plan' => null]);
    }

    public function store(GuardarPlanCuotaRequest $request, MedioPago $medio, RegistradorAuditoria $auditoria): RedirectResponse
    {
        $this->exigirTarjetaDeCredito($medio);

        DB::transaction(function () use ($request, $medio, $auditoria) {
            $plan = $medio->planes()->create([
                'cuotas' => (int) $request->validated('cuotas'),
                'recargo_pct' => $request->recargo(),
                'activo' => $request->boolean('activo', true),
            ]);

            $auditoria->registrar('caja.planes_cuota.creado', $plan, null, $plan->only('medio_pago_id', 'cuotas', 'recargo_pct', 'activo'));
        });

        return redirect()->route('caja.medios-pago.edit', $medio)->with('status', 'Plan de cuotas creado.');
    }

    public function edit(MedioPago $medio, PlanCuota $plan): View
    {
        Gate::authorize(Permiso::CajaConfigurar->value);
        $this->exigirPlanDelMedio($medio, $plan);

        return view('caja.planes.form', ['medio' => $medio, 'plan' => $plan]);
    }

    public function update(GuardarPlanCuotaRequest $request, MedioPago $medio, PlanCuota $plan, RegistradorAuditoria $auditoria): RedirectResponse
    {
        $this->exigirPlanDelMedio($medio, $plan);

        DB::transaction(function () use ($request, $plan, $auditoria) {
            $plan->fill([
                'cuotas' => (int) $request->validated('cuotas'),
                'recargo_pct' => $request->recargo(),
                'activo' => $request->boolean('activo'),
            ]);
            $cambios = $plan->getDirty();

            if ($cambios === []) {
                return;
            }

            $antes = array_intersect_key($plan->getOriginal(), $cambios);
            $plan->save();

            $auditoria->registrar('caja.planes_cuota.actualizado', $plan, $antes, $cambios);
        });

        return redirect()->route('caja.medios-pago.edit', $medio)->with('status', 'Cambios guardados.');
    }

    private function exigirTarjetaDeCredito(MedioPago $medio): void
    {
        abort_unless($medio->tipo->admitePlanes(), 404);
    }

    private function exigirPlanDelMedio(MedioPago $medio, PlanCuota $plan): void
    {
        abort_unless((int) $plan->medio_pago_id === (int) $medio->id, 404);
    }
}
