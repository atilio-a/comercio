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

    /**
     * Una venta con devoluciones no se anula: la anulación revertiría también lo ya devuelto.
     */
    public function anular(User $user, Venta $venta): bool
    {
        return $user->can(Permiso::VentasAnular->value)
            && ! $venta->estaAnulada()
            && ! $venta->devoluciones()->exists();
    }

    /**
     * Devolver o cambiar ítems (VEN-03): `ventas.devolver`, poder ver la venta y que no esté
     * anulada. El reembolso en dinero exige además `ventas.reembolsar`.
     */
    public function devolver(User $user, Venta $venta): bool
    {
        return $user->can(Permiso::VentasDevolver->value)
            && ! $venta->estaAnulada()
            && $this->view($user, $venta);
    }
}
