<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Una venta o su anulación no se puede registrar (pagos que no suman, descuento mayor que el
 * importe, saldo a favor insuficiente, venta ya anulada…). `campo` indica dónde mostrar el
 * mensaje en el formulario.
 */
class VentaRechazada extends RuntimeException
{
    public function __construct(string $mensaje, public readonly string $campo = 'items')
    {
        parent::__construct($mensaje);
    }
}
