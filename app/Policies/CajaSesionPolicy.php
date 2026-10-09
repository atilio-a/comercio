<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\CajaSesion;
use App\Models\User;
use App\Services\SucursalActiva;

/**
 * Sesiones de caja. Quien supervisa ve todas las de la sucursal activa; quien opera,
 * solo las propias. El Administrador pasa siempre por `Gate::before`, por eso los
 * controladores además filtran por sucursal activa.
 */
class CajaSesionPolicy
{
    public function __construct(private SucursalActiva $sucursalActiva) {}

    public function viewAny(User $user): bool
    {
        return $user->can(Permiso::CajaSupervisar->value);
    }

    public function view(User $user, CajaSesion $sesion): bool
    {
        if ((int) $sesion->sucursal_id !== (int) $this->sucursalActiva->id()) {
            return false;
        }

        return $user->can(Permiso::CajaSupervisar->value)
            || ((int) $sesion->user_id === (int) $user->id && $user->can(Permiso::CajaOperar->value));
    }
}
