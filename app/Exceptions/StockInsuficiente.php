<?php

namespace App\Exceptions;

use App\Models\Variante;
use RuntimeException;

/**
 * Un movimiento dejaría el stock en negativo y el parámetro "Permitir venta con stock
 * negativo" está deshabilitado.
 */
class StockInsuficiente extends RuntimeException
{
    public function __construct(
        public readonly Variante $variante,
        public readonly int $disponible,
    ) {
        parent::__construct(sprintf(
            'Stock insuficiente de %s (%s): hay %d disponibles.',
            $variante->producto->nombre,
            $variante->descripcion(),
            $disponible,
        ));
    }
}
