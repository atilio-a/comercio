<?php

namespace App\Enums;

/**
 * Cómo se resuelve una devolución (VEN-03). Las dos emiten nota de crédito: con saldo a favor
 * su importe se acredita en la cuenta corriente del cliente; con reembolso sale de la caja.
 */
enum ResolucionDevolucion: string
{
    case SaldoAFavor = 'nota_credito_saldo_a_favor';
    case Reembolso = 'reembolso';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SaldoAFavor => 'Saldo a favor en cuenta corriente',
            self::Reembolso => 'Reembolso en dinero',
        };
    }
}
