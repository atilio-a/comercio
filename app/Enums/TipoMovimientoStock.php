<?php

namespace App\Enums;

/**
 * Tipos de movimiento de stock (STK-02).
 */
enum TipoMovimientoStock: string
{
    case Ingreso = 'ingreso';
    case Egreso = 'egreso';
    case Ajuste = 'ajuste';
    case Transferencia = 'transferencia';

    /**
     * Signo que debe tener la cantidad: 1 positivo, -1 negativo, 0 cualquiera.
     */
    public function signo(): int
    {
        return match ($this) {
            self::Ingreso => 1,
            self::Egreso => -1,
            self::Ajuste, self::Transferencia => 0,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ingreso => 'Ingreso',
            self::Egreso => 'Egreso',
            self::Ajuste => 'Ajuste',
            self::Transferencia => 'Transferencia',
        };
    }
}
