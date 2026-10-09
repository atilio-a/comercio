<?php

namespace App\Enums;

/**
 * Estado de una sesión de caja (CAJ-02).
 */
enum EstadoSesionCaja: string
{
    case Abierta = 'abierta';
    case Cerrada = 'cerrada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Abierta => 'Abierta',
            self::Cerrada => 'Cerrada',
        };
    }
}
