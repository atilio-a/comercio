<?php

namespace App\Enums;

/**
 * Estado de una venta. Una venta confirmada solo puede pasar a anulada (VEN-05).
 */
enum EstadoVenta: string
{
    case Confirmada = 'confirmada';
    case Anulada = 'anulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Confirmada => 'Confirmada',
            self::Anulada => 'Anulada',
        };
    }
}
