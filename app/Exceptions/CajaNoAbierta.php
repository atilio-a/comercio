<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se intentó registrar dinero sin una sesión de caja abierta, o en una sesión cerrada.
 */
class CajaNoAbierta extends RuntimeException
{
    public function __construct(string $mensaje = 'No tenés una caja abierta en esta sucursal.')
    {
        parent::__construct($mensaje);
    }
}
