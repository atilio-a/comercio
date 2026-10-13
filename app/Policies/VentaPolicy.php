<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\User;
use App\Models\Venta;

/**
 * Ventas (doc 02, roles): con `ventas.ver_todas` se ven todas las de la sucursal; el Vendedor
 * solo las suyas (como vendedor o porque las registró). La sucursal la controla el
 * controlador (las de otra sucursal responden 404). El Administrador pasa por `Gate::before`.
 */
class VentaPolicy
{
    public function view(User $user, Venta $venta): bool
    {
        if ($user->can(Permiso::VentasVerTodas->value)) {
            return true;
        }

        return $user->can(Permiso::VentasVender->value) && $venta->esPropiaDe($user);
    }

    public function anular(User $user, Venta $venta): bool
    {
        return $user->can(Permiso::VentasAnular->value) && ! $venta->estaAnulada();
    }
}
