<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Una devolución no se puede registrar (venta anulada, cantidad mayor que la devolvible, falta
 * el cliente para el saldo a favor, reembolso sin permiso…). `campo` indica dónde mostrar el
 * mensaje en el formulario.
 */
class DevolucionRechazada extends RuntimeException
{
    public function __construct(string $mensaje, public readonly string $campo = 'cantidades')
    {
        parent::__construct($mensaje);
    }
}
