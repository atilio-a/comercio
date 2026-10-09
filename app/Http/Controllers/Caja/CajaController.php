<?php

namespace App\Http\Controllers\Caja;

use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Caja\GuardarCajaRequest;
use App\Models\Caja;
use App\Services\RegistradorAuditoria;
use App\Services\SucursalActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Cajas de la sucursal activa. Solo con `caja.configurar`.
 */
class CajaController extends Controller
{
    public function __construct(private SucursalActiva $sucursalActiva) {}

    public function index(): View
    {
        Gate::authorize(Permiso::CajaConfigurar->value);

        return view('caja.cajas.index', [
            'cajas' => Caja::query()
                ->where('sucursal_id', $this->sucursalActiva->id())
                ->with('sesionAbierta.user:id,name')
                ->orderByDesc('activa')
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize(Permiso::CajaConfigurar->value);

        return view('caja.cajas.form', ['caja' => null]);
    }

    public function store(GuardarCajaRequest $request, RegistradorAuditoria $auditoria): RedirectResponse
    {
        DB::transaction(function () use ($request, $auditoria) {
            $caja = Caja::create([
                'sucursal_id' => $this->sucursalActiva->id(),
                'nombre' => trim($request->validated('nombre')),
                'activa' => $request->boolean('activa', true),
            ]);

            $auditoria->registrar('caja.cajas.creada', $caja, null, $caja->only('sucursal_id', 'nombre', 'activa'));
        });

        return redirect()->route('caja.cajas.index')->with('status', 'Caja creada.');
    }

    public function edit(Caja $caja): View
    {
        Gate::authorize(Permiso::CajaConfigurar->value);
        $this->exigirSucursalActiva($caja);

        return view('caja.cajas.form', ['caja' => $caja]);
    }

    public function update(GuardarCajaRequest $request, Caja $caja, RegistradorAuditoria $auditoria): RedirectResponse
    {
        $this->exigirSucursalActiva($caja);

        DB::transaction(function () use ($request, $caja, $auditoria) {
            $caja->fill([
                'nombre' => trim($request->validated('nombre')),
                'activa' => $request->boolean('activa'),
            ]);
            $cambios = $caja->getDirty();

            if ($cambios === []) {
                return;
            }

            $antes = array_intersect_key($caja->getOriginal(), $cambios);
            $caja->save();

            $auditoria->registrar('caja.cajas.actualizada', $caja, $antes, $cambios);
        });

        return redirect()->route('caja.cajas.index')->with('status', 'Cambios guardados.');
    }

    private function exigirSucursalActiva(Caja $caja): void
    {
        abort_unless((int) $caja->sucursal_id === (int) $this->sucursalActiva->id(), 404);
    }
}
