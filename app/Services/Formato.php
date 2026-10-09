<?php

namespace App\Services;

/**
 * Formato argentino para mostrar números (punto de miles, coma decimal).
 * Solo para presentación: los cálculos usan {@see CalculadoraIva}.
 */
class Formato
{
    /**
     * "89900.5" → "89.900,50".
     */
    public static function importe(string|int|float|null $valor): string
    {
        return number_format((float) $valor, 2, ',', '.');
    }

    /**
     * "10.50" → "10,5 %", "21.00" → "21 %".
     */
    public static function porcentaje(string|int|float|null $valor): string
    {
        $texto = rtrim(rtrim(number_format((float) $valor, 2, ',', '.'), '0'), ',');

        return $texto.' %';
    }
}
