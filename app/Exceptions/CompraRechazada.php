<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Una operación de compras no se puede hacer en el estado actual (orden no recibible,
 * cantidad mayor que lo pendiente, recepción ya anulada…). `campo` indica dónde mostrar
 * el mensaje en el formulario.
 */
class CompraRechazada extends RuntimeException
{
    public function __construct(string $mensaje, public readonly string $campo = 'items')
    {
        parent::__construct($mensaje);
    }
}
