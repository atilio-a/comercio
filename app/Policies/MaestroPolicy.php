<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Tablas maestras del catálogo (categorías, marcas, temporadas, colores y alícuotas).
 * Todas sus operaciones requieren `catalogo.gestionar`.
 */
class MaestroPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permiso::CatalogoGestionar->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permiso::CatalogoGestionar->value);
    }

    public function update(User $user, Model $registro): bool
    {
        return $user->can(Permiso::CatalogoGestionar->value);
    }
}
