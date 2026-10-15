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
     * Permisos asignados al rol. El Administrador tiene todos por `Gate::before`;
     * `caja.configurar` y `puntos_venta.configurar` no se asignan a otros roles: configurar medios de pago
     * y puntos de venta es del Administrador.
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
                Permiso::CajaOperar,
                Permiso::CajaSupervisar,
                Permiso::ComprasVer,
                Permiso::ComprasGestionar,
                Permiso::ComprasRecibir,
                Permiso::ClientesVer,
                Permiso::ClientesGestionar,
                Permiso::CuentasCorrientesVer,
                Permiso::VentasVender,
                Permiso::VentasVerTodas,
                Permiso::VentasAutorizarDescuentos,
                Permiso::VentasAnular,
                Permiso::VentasDevolver,
                Permiso::VentasReembolsar,
            ],
            self::Deposito => [
                Permiso::CatalogoVer,
                Permiso::StockVer,
                Permiso::StockAjustar,
                Permiso::ComprasVer,
                Permiso::ComprasRecibir,
            ],
            self::Vendedor => [
                Permiso::CatalogoVer,
                Permiso::StockVer,
                Permiso::CajaOperar,
                Permiso::ClientesVer,
                Permiso::ClientesGestionar,
                Permiso::VentasVender,
                Permiso::VentasDevolver,
            ],
            self::Cajero => [
                Permiso::CatalogoVer,
                Permiso::StockVer,
                Permiso::CajaOperar,
                Permiso::ClientesVer,
                Permiso::ClientesGestionar,
                Permiso::CuentasCorrientesVer,
                Permiso::VentasVender,
                Permiso::VentasVerTodas,
                Permiso::VentasDevolver,
            ],
        };
    }
}
