<?php

namespace App\Services;

use App\Http\Middleware\EstablecerSucursalActiva;
use App\Models\Sucursal;
use App\Models\User;

/**
 * Sucursal en la que opera el usuario durante la solicitud actual.
 *
 * En HTTP la fija el middleware {@see EstablecerSucursalActiva}
 * a partir de la sesión. En consola o jobs hay que fijarla con {@see establecer()}
 * antes de consultar permisos o grabar operaciones.
 */
class SucursalActiva
{
    public const CLAVE_SESION = 'sucursal_activa_id';

    private ?Sucursal $sucursal = null;

    public function sucursal(): ?Sucursal
    {
        return $this->sucursal;
    }

    public function id(): ?int
    {
        return $this->sucursal?->id;
    }

    /**
     * Fija la sucursal para esta solicitud y los permisos de spatie (team = sucursal).
     */
    public function establecer(Sucursal $sucursal, ?User $user = null): void
    {
        $this->sucursal = $sucursal;

        setPermissionsTeamId($sucursal->id);

        // Roles and permissions loaded for another branch must not be reused.
        $user?->unsetRelation('roles')->unsetRelation('permissions');
    }

    public function olvidar(): void
    {
        $this->sucursal = null;

        setPermissionsTeamId(null);
    }
}
