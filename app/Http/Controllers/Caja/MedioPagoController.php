<?php

namespace App\Http\Controllers\Caja;

use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Caja\GuardarMedioPagoRequest;
use App\Models\MedioPago;
use App\Services\RegistradorAuditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Catálogo de medios de pago (CAJ-01). Solo con `caja.configurar`.
 */
class MedioPagoController extends Controller
{
    public function index(): View
    {
        Gate::authorize(Permiso::CajaConfigurar->value);

        return view('caja.medios-pago.index', [
            'medios' => MedioPago::query()->withCount('planes')->orderByDesc('activo')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize(Permiso::CajaConfigurar->value);

        return view('caja.medios-pago.form', ['medio' => null]);
    }

    public function store(GuardarMedioPagoRequest $request, RegistradorAuditoria $auditoria): RedirectResponse
    {
        DB::transaction(function () use ($request, $auditoria) {
            $medio = MedioPago::create($request->datos(activoPorDefecto: true));

            $auditoria->registrar('caja.medios_pago.creado', $medio, null, $medio->only(
                'nombre', 'tipo', 'impacta_caja', 'dias_acreditacion', 'requiere_referencia', 'activo',
            ));
        });

        return redirect()->route('caja.medios-pago.index')->with('status', 'Medio de pago creado.');
    }

    public function edit(MedioPago $medio): View
    {
        Gate::authorize(Permiso::CajaConfigurar->value);

        return view('caja.medios-pago.form', [
            'medio' => $medio,
            'planes' => $medio->planes()->orderBy('cuotas')->get(),
        ]);
    }

    public function update(GuardarMedioPagoRequest $request, MedioPago $medio, RegistradorAuditoria $auditoria): RedirectResponse
    {
        DB::transaction(function () use ($request, $medio, $auditoria) {
            $medio->fill($request->datos(activoPorDefecto: false));
            $cambios = $medio->getDirty();

            if ($cambios === []) {
                return;
            }

            $antes = array_intersect_key($medio->getOriginal(), $cambios);
            $medio->save();

            $auditoria->registrar('caja.medios_pago.actualizado', $medio, $antes, $cambios);
        });

        return redirect()->route('caja.medios-pago.index')->with('status', 'Cambios guardados.');
    }
}
