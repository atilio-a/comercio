<?php

namespace Database\Seeders;

use App\Enums\Permiso as PermisoEnum;
use App\Enums\Rol as RolEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesYPermisosSeeder extends Seeder
{
    /**
     * Crea roles y permisos globales (sin sucursal) y sincroniza la matriz rol-permiso.
     * La asignación de un rol a un usuario sí lleva la sucursal.
     */
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $sucursalAnterior = getPermissionsTeamId();
        setPermissionsTeamId(null);

        foreach (PermisoEnum::cases() as $permiso) {
            Permission::firstOrCreate(['name' => $permiso->value, 'guard_name' => 'web']);
        }

        foreach (RolEnum::cases() as $rol) {
            $role = Role::query()
                ->whereNull($registrar->teamsKey)
                ->firstOrCreate(['name' => $rol->value, 'guard_name' => 'web']);

            $role->syncPermissions(array_map(fn (PermisoEnum $permiso) => $permiso->value, $rol->permisos()));
        }

        setPermissionsTeamId($sucursalAnterior);
        $registrar->forgetCachedPermissions();
    }
}
