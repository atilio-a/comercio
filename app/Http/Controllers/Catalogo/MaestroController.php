<?php

namespace App\Http\Controllers\Catalogo;

use App\Enums\TablaMaestra;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\GuardarMaestroRequest;
use App\Services\RegistradorAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Alta, edición y activación de las tablas maestras simples (ver {@see TablaMaestra}).
 */
class MaestroController extends Controller
{
    public function index(TablaMaestra $tabla): View
    {
        Gate::authorize('viewAny', $tabla->modelo());

        return view('catalogo.maestros.index', [
            'tabla' => $tabla,
            'registros' => $tabla->modelo()::query()->orderByDesc('activo')->orderBy('nombre')->paginate(50),
        ]);
    }

    public function create(TablaMaestra $tabla): View
    {
        Gate::authorize('create', $tabla->modelo());

        return view('catalogo.maestros.form', ['tabla' => $tabla, 'registro' => null]);
    }

    public function store(GuardarMaestroRequest $request, TablaMaestra $tabla, RegistradorAuditoria $auditoria): RedirectResponse
    {
        DB::transaction(function () use ($request, $tabla, $auditoria) {
            $registro = $tabla->modelo()::create([
                'nombre' => $request->validated('nombre'),
                'activo' => $request->boolean('activo', true),
            ]);

            $auditoria->registrar("catalogo.{$tabla->value}.creado", $registro, null, $registro->only('nombre', 'activo'));
        });

        return redirect()->route('catalogo.maestros.index', $tabla)->with('status', 'Registro creado.');
    }

    public function edit(TablaMaestra $tabla, int $id): View
    {
        $registro = $this->buscar($tabla, $id);
        Gate::authorize('update', $registro);

        return view('catalogo.maestros.form', ['tabla' => $tabla, 'registro' => $registro]);
    }

    public function update(GuardarMaestroRequest $request, TablaMaestra $tabla, int $id, RegistradorAuditoria $auditoria): RedirectResponse
    {
        $registro = $this->buscar($tabla, $id);

        DB::transaction(function () use ($request, $tabla, $registro, $auditoria) {
            $registro->fill([
                'nombre' => $request->validated('nombre'),
                'activo' => $request->boolean('activo'),
            ]);

            $cambios = $registro->getDirty();

            if ($cambios === []) {
                return;
            }

            $antes = array_intersect_key($registro->getOriginal(), $cambios);
            $registro->save();

            $auditoria->registrar("catalogo.{$tabla->value}.actualizado", $registro, $antes, $cambios);
        });

        return redirect()->route('catalogo.maestros.index', $tabla)->with('status', 'Cambios guardados.');
    }

    private function buscar(TablaMaestra $tabla, int $id): Model
    {
        return $tabla->modelo()::query()->findOrFail($id);
    }
}
