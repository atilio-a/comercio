<?php

namespace App\Enums;

use App\Services\MovimientosCaja;

/**
 * Tipos de movimiento de caja (CAJ-02, CAJ-03). Los de venta, devolución, cobranza y
 * pago a proveedor los registran esos módulos con {@see MovimientosCaja}.
 */
enum TipoMovimientoCaja: string
{
    case Apertura = 'apertura';
    case Ingreso = 'ingreso';
    case Egreso = 'egreso';
    case Venta = 'venta';
    case Devolucion = 'devolucion';
    case Cobranza = 'cobranza';
    case PagoProveedor = 'pago_proveedor';

    /**
     * Signo que debe tener el importe: 1 positivo, -1 negativo. Un contraasiento lleva
     * el signo opuesto al del movimiento que revierte.
     */
    public function signo(): int
    {
        return match ($this) {
            self::Apertura, self::Ingreso, self::Venta, self::Cobranza => 1,
            self::Egreso, self::Devolucion, self::PagoProveedor => -1,
        };
    }

    /**
     * Ingresos y egresos varios llevan siempre motivo (CAJ-03).
     */
    public function exigeMotivo(): bool
    {
        return in_array($this, [self::Ingreso, self::Egreso], true);
    }

    /**
     * Movimientos cargados a mano en la caja (no generados por otro módulo).
     */
    public function esManual(): bool
    {
        return in_array($this, [self::Apertura, self::Ingreso, self::Egreso], true);
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Apertura => 'Apertura',
            self::Ingreso => 'Ingreso',
            self::Egreso => 'Egreso',
            self::Venta => 'Venta',
            self::Devolucion => 'Devolución',
            self::Cobranza => 'Cobranza',
            self::PagoProveedor => 'Pago a proveedor',
        };
    }
}
