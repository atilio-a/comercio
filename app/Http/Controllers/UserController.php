<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\GestionUsuarios;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Listado de usuarios con su rol en cada sucursal.
     */
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        $usuarios = User::query()
            ->with(['sucursales' => fn ($query) => $query->orderBy('sucursales.id'), 'vendedor'])
            ->orderBy('name')
            ->paginate(25);

        return view('usuarios.index', [
            'usuarios' => $usuarios,
            'roles' => $this->rolesDe($usuarios->getCollection()),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('usuarios.create', [
            'sucursales' => Sucursal::activas()->orderBy('id')->get(),
            'opcionesRol' => Rol::cases(),
        ]);
    }

    public function store(StoreUserRequest $request, GestionUsuarios $gestion): RedirectResponse
    {
        $gestion->crear($request->datosUsuario(), $request->rolesPorSucursal());

        return redirect()->route('usuarios.index')->with('status', 'Usuario creado.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        $sucursales = Sucursal::activas()->orderBy('id')->get();

        return view('usuarios.edit', [
            'usuario' => $user,
            'sucursales' => $sucursales,
            'opcionesRol' => Rol::cases(),
            'rolesActuales' => $sucursales->mapWithKeys(fn (Sucursal $sucursal) => [
                $sucursal->id => $user->rolEn($sucursal)?->value,
            ])->all(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, GestionUsuarios $gestion): RedirectResponse
    {
        $gestion->actualizar($user, $request->datosUsuario(), $request->rolesPorSucursal());

        return redirect()->route('usuarios.index')->with('status', 'Usuario actualizado.');
    }

    /**
     * Nombre del rol por usuario y sucursal, en una sola consulta.
     *
     * @param  Collection<int, User>  $usuarios
     * @return array<int, array<int, string>>
     */
    private function rolesDe(Collection $usuarios): array
    {
        $tablas = config('permission.table_names');
        $equipo = config('permission.column_names.team_foreign_key');

        return DB::table($tablas['model_has_roles'].' as mhr')
            ->join($tablas['roles'].' as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', (new User)->getMorphClass())
            ->whereIn('mhr.model_id', $usuarios->modelKeys())
            ->get(['mhr.model_id', 'mhr.'.$equipo.' as sucursal_id', 'r.name'])
            ->groupBy('model_id')
            ->map(fn (Collection $filas) => $filas->pluck('name', 'sucursal_id')->all())
            ->all();
    }
}
