<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La caja ya tiene una sesión abierta, o el usuario ya tiene otra caja abierta en la sucursal.
 */
class CajaYaAbierta extends RuntimeException {}
