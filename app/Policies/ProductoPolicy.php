<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\Producto;
use App\Models\User;

/**
 * Productos y sus variantes. El Administrador pasa siempre por `Gate::before`.
 */
class ProductoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permiso::CatalogoVer->value);
    }

    public function view(User $user, Producto $producto): bool
    {
        return $user->can(Permiso::CatalogoVer->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permiso::CatalogoGestionar->value);
    }

    /**
     * También cubre agregar y editar variantes del producto.
     */
    public function update(User $user, Producto $producto): bool
    {
        return $user->can(Permiso::CatalogoGestionar->value);
    }
}
