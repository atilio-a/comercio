<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\User;

/**
 * Gestión de usuarios. El Administrador pasa siempre por `Gate::before`.
 */
class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permiso::UsuariosVer->value) || $user->can(Permiso::UsuariosGestionar->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(Permiso::UsuariosGestionar->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can(Permiso::UsuariosGestionar->value);
    }
}
