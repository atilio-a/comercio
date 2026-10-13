<?php

namespace App\Http\Controllers\Compras;

use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Compras\GuardarProveedorRequest;
use App\Models\Proveedor;
use App\Services\Cuit;
use App\Services\RegistradorAuditoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Catálogo de proveedores (COM-01): lo ve quien tiene `compras.ver` y lo gestiona
 * quien tiene `compras.gestionar`.
 */
class ProveedorController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize(Permiso::ComprasVer->value);

        $filtros = $request->validate(['q' => ['nullable', 'string', 'max:150']]);

        $proveedores = Proveedor::query()
            ->when($filtros['q'] ?? null, function (Builder $query, string $texto) {
                $query->where(function (Builder $query) use ($texto) {
                    $query->where('razon_social', 'like', '%'.$texto.'%')
                        ->orWhere('cuit', Cuit::normalizar($texto));
                });
            })
            ->orderByDesc('activo')
            ->orderBy('razon_social')
            ->paginate(50)
            ->withQueryString();

        return view('compras.proveedores.index', [
            'proveedores' => $proveedores,
            'filtros' => $filtros,
            'puedeGestionar' => $request->user()->can(Permiso::ComprasGestionar->value),
        ]);
    }

    public function create(): View
    {
        Gate::authorize(Permiso::ComprasGestionar->value);

        return view('compras.proveedores.form', ['proveedor' => null]);
    }

    public function store(GuardarProveedorRequest $request, RegistradorAuditoria $auditoria): RedirectResponse
    {
        DB::transaction(function () use ($request, $auditoria) {
            $proveedor = Proveedor::create($request->datos(activoPorDefecto: true));

            $auditoria->registrar('compras.proveedores.creado', $proveedor, null, $proveedor->only(
                'razon_social', 'cuit', 'contacto', 'telefono', 'email', 'condiciones_pago', 'activo',
            ));
        });

        return redirect()->route('compras.proveedores.index')->with('status', 'Proveedor creado.');
    }

    public function edit(Proveedor $proveedor): View
    {
        Gate::authorize(Permiso::ComprasGestionar->value);

        return view('compras.proveedores.form', ['proveedor' => $proveedor]);
    }

    public function update(GuardarProveedorRequest $request, Proveedor $proveedor, RegistradorAuditoria $auditoria): RedirectResponse
    {
        DB::transaction(function () use ($request, $proveedor, $auditoria) {
            $proveedor->fill($request->datos(activoPorDefecto: false));
            $cambios = $proveedor->getDirty();

            if ($cambios === []) {
                return;
            }

            $antes = array_intersect_key($proveedor->getOriginal(), $cambios);
            $proveedor->save();

            $auditoria->registrar('compras.proveedores.actualizado', $proveedor, $antes, $cambios);
        });

        return redirect()->route('compras.proveedores.index')->with('status', 'Cambios guardados.');
    }
}
