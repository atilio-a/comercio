<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Las transferencias entre sucursales están modeladas pero deshabilitadas.
 */
class TransferenciaNoHabilitada extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Las transferencias entre sucursales no están habilitadas.');
    }
}
