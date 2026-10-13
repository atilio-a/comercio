<?php

namespace App\Exceptions;

use App\Models\CuentaCorriente;
use App\Services\Formato;
use RuntimeException;

/**
 * Un débito dejaría deuda en una cuenta corriente que no la admite: sin crédito habilitado,
 * bloqueada o por encima del límite. `disponible` es lo que todavía se puede debitar
 * (saldo a favor más el crédito libre, si corresponde).
 */
class CreditoNoDisponible extends RuntimeException
{
    public function __construct(
        public readonly CuentaCorriente $cuenta,
        public readonly string $disponible,
        string $mensaje,
    ) {
        parent::__construct($mensaje);
    }

    public static function sinCredito(CuentaCorriente $cuenta, string $saldoAFavor): self
    {
        return new self($cuenta, $saldoAFavor, sprintf(
            'El cliente no tiene crédito habilitado: solo puede usar su saldo a favor de $ %s.',
            Formato::importe($saldoAFavor),
        ));
    }

    public static function bloqueada(CuentaCorriente $cuenta, string $saldoAFavor): self
    {
        return new self($cuenta, $saldoAFavor, sprintf(
            'La cuenta corriente del cliente está bloqueada: solo puede usar su saldo a favor de $ %s.',
            Formato::importe($saldoAFavor),
        ));
    }

    public static function limiteSuperado(CuentaCorriente $cuenta, string $disponible): self
    {
        return new self($cuenta, $disponible, sprintf(
            'El débito supera el límite de crédito del cliente: hay $ %s disponibles.',
            Formato::importe($disponible),
        ));
    }
}
