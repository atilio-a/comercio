<?php

namespace App\Services;

use App\Enums\Rol;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Alta y edición de usuarios con sus roles por sucursal, auditadas en la misma transacción.
 */
class GestionUsuarios
{
    public function __construct(
        private RegistradorAuditoria $auditoria,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string, activo?: bool}  $datos
     * @param  array<int, Rol|null>  $roles  Rol por id de sucursal; null o ausente = sin asignar.
     */
    public function crear(array $datos, array $roles): User
    {
        return DB::transaction(function () use ($datos, $roles) {
            $user = User::create([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => $datos['password'],
                'activo' => $datos['activo'] ?? true,
            ]);

            $this->sincronizarRoles($user, $roles);

            $this->auditoria->registrar('usuario.creado', $user, null, [
                'name' => $user->name,
                'email' => $user->email,
                'activo' => $user->activo,
                'roles' => $this->rolesPorSucursal($user),
            ]);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, password?: string|null, activo: bool}  $datos
     * @param  array<int, Rol|null>  $roles  Rol por id de sucursal; solo se tocan las sucursales incluidas.
     */
    public function actualizar(User $user, array $datos, array $roles): User
    {
        return DB::transaction(function () use ($user, $datos, $roles) {
            $rolesAntes = $this->rolesPorSucursal($user);

            $user->fill([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'activo' => $datos['activo'],
            ]);

            if (! empty($datos['password'])) {
                $user->password = $datos['password'];
            }

            $cambios = $user->getDirty();
            $antes = array_intersect_key($user->getOriginal(), $cambios);
            $activoAntes = (bool) $user->getOriginal('activo');

            $user->save();

            $this->sincronizarRoles($user, $roles);
            $rolesDespues = $this->rolesPorSucursal($user);

            if ($rolesAntes !== $rolesDespues) {
                $antes['roles'] = $rolesAntes;
                $cambios['roles'] = $rolesDespues;
            }

            if ($cambios === []) {
                return $user;
            }

            $accion = match (true) {
                $activoAntes && ! $user->activo => 'usuario.desactivado',
                ! $activoAntes && $user->activo => 'usuario.activado',
                default => 'usuario.actualizado',
            };

            $this->auditoria->registrar($accion, $user, $antes, $cambios);

            return $user;
        });
    }

    /**
     * Roles del usuario por nombre de sucursal, para mostrar y auditar.
     *
     * @return array<string, string>
     */
    public function rolesPorSucursal(User $user): array
    {
        $roles = [];

        foreach ($user->sucursales()->orderBy('sucursales.id')->get() as $sucursal) {
            $roles[$sucursal->nombre] = $user->rolEn($sucursal)?->value;
        }

        return array_filter($roles);
    }

    /**
     * @param  array<int, Rol|null>  $roles
     */
    private function sincronizarRoles(User $user, array $roles): void
    {
        $sucursales = Sucursal::query()->whereKey(array_keys($roles))->get()->keyBy('id');

        foreach ($roles as $sucursalId => $rol) {
            $sucursal = $sucursales->get($sucursalId);

            if (! $sucursal) {
                continue;
            }

            if ($rol) {
                $user->sucursales()->syncWithoutDetaching([$sucursal->id]);
                $user->asignarRol($rol, $sucursal);
            } else {
                $user->quitarRol($sucursal);
                $user->sucursales()->detach($sucursal->id);
            }
        }
    }
}
