<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\GuardarAlicuotaIvaRequest;
use App\Models\AlicuotaIva;
use App\Services\RegistradorAuditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AlicuotaIvaController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', AlicuotaIva::class);

        return view('catalogo.alicuotas.index', [
            'alicuotas' => AlicuotaIva::query()->withCount('productos')->orderByDesc('activo')->orderBy('porcentaje')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', AlicuotaIva::class);

        return view('catalogo.alicuotas.form', ['alicuota' => null, 'enUso' => false]);
    }

    public function store(GuardarAlicuotaIvaRequest $request, RegistradorAuditoria $auditoria): RedirectResponse
    {
        DB::transaction(function () use ($request, $auditoria) {
            $alicuota = AlicuotaIva::create([
                'nombre' => $request->validated('nombre'),
                'porcentaje' => $request->porcentaje(),
                'activo' => $request->boolean('activo', true),
            ]);

            $auditoria->registrar('catalogo.alicuotas_iva.creado', $alicuota, null, $alicuota->only('nombre', 'porcentaje', 'activo'));
        });

        return redirect()->route('catalogo.alicuotas.index')->with('status', 'Alícuota creada.');
    }

    public function edit(AlicuotaIva $alicuota): View
    {
        Gate::authorize('update', $alicuota);

        return view('catalogo.alicuotas.form', [
            'alicuota' => $alicuota,
            'enUso' => $alicuota->productos()->exists(),
        ]);
    }

    public function update(GuardarAlicuotaIvaRequest $request, AlicuotaIva $alicuota, RegistradorAuditoria $auditoria): RedirectResponse
    {
        DB::transaction(function () use ($request, $alicuota, $auditoria) {
            $alicuota->fill([
                'nombre' => $request->validated('nombre'),
                'porcentaje' => $request->porcentaje(),
                'activo' => $request->boolean('activo'),
            ]);

            $cambios = $alicuota->getDirty();

            if ($cambios === []) {
                return;
            }

            $antes = array_intersect_key($alicuota->getOriginal(), $cambios);
            $alicuota->save();

            $auditoria->registrar('catalogo.alicuotas_iva.actualizado', $alicuota, $antes, $cambios);
        });

        return redirect()->route('catalogo.alicuotas.index')->with('status', 'Cambios guardados.');
    }
}
