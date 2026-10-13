<?php

namespace App\Enums;

/**
 * Tipo de movimiento de cuenta corriente. El debe aumenta lo que el cliente debe; el haber
 * lo reduce o genera saldo a favor.
 */
enum TipoMovimientoCuenta: string
{
    case Debe = 'debe';
    case Haber = 'haber';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Debe => 'Debe',
            self::Haber => 'Haber',
        };
    }

    /**
     * Efecto sobre el saldo (Σ debe − Σ haber): +1 o −1.
     */
    public function signo(): int
    {
        return $this === self::Debe ? 1 : -1;
    }

    public function opuesto(): self
    {
        return $this === self::Debe ? self::Haber : self::Debe;
    }
}
