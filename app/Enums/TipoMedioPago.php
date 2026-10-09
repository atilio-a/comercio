<?php

namespace App\Enums;

/**
 * Tipos de medio de pago (CAJ-01). Cada medio del catálogo pertenece a uno.
 */
enum TipoMedioPago: string
{
    case Efectivo = 'efectivo';
    case TarjetaDebito = 'tarjeta_debito';
    case TarjetaCredito = 'tarjeta_credito';
    case Transferencia = 'transferencia';
    case Qr = 'qr';
    case CuentaCorriente = 'cuenta_corriente';
    case SaldoAFavor = 'saldo_a_favor';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::TarjetaDebito => 'Tarjeta de débito',
            self::TarjetaCredito => 'Tarjeta de crédito',
            self::Transferencia => 'Transferencia',
            self::Qr => 'QR / billetera',
            self::CuentaCorriente => 'Cuenta corriente',
            self::SaldoAFavor => 'Saldo a favor',
        };
    }

    /**
     * Solo las tarjetas de crédito tienen planes de cuotas.
     */
    public function admitePlanes(): bool
    {
        return $this === self::TarjetaCredito;
    }

    /**
     * Si se puede usar en ingresos y egresos varios de caja. La cuenta corriente y el
     * saldo a favor viven en la cuenta del cliente, no en la caja.
     */
    public function admiteMovimientoManual(): bool
    {
        return ! in_array($this, [self::CuentaCorriente, self::SaldoAFavor], true);
    }
}
