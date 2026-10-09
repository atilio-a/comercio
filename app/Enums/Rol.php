<?php

namespace App\Enums;

/**
 * Roles base del comercio (USR-01). El valor es el nombre del rol en spatie/laravel-permission.
 */
enum Rol: string
{
    case Administrador = 'Administrador';
    case Gerente = 'Gerente';
    case Vendedor = 'Vendedor';
    case Cajero = 'Cajero';
    case Deposito = 'Depósito';

    /**
     * Permisos asignados al rol. El Administrador tiene todos por `Gate::before`.
     *
     * @return list<Permiso>
     */
    public function permisos(): array
    {
        return match ($this) {
            self::Administrador => Permiso::cases(),
            self::Gerente => [
                Permiso::UsuariosVer,
                Permiso::AuditoriaVer,
                Permiso::CatalogoVer,
                Permiso::CatalogoGestionar,
                Permiso::StockVer,
                Permiso::StockAjustar,
            ],
            self::Deposito => [Permiso::CatalogoVer, Permiso::StockVer, Permiso::StockAjustar],
            self::Vendedor, self::Cajero => [Permiso::CatalogoVer, Permiso::StockVer],
        };
    }
}
