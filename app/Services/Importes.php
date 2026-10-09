<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Conversión exacta entre importes decimales (como los guarda la base, "1200.50")
 * y centavos enteros, para sumar y restar dinero sin punto flotante.
 */
class Importes
{
    /**
     * Regla de validación para importes ingresados en formularios: sin signo, hasta 12
     * dígitos enteros y dos decimales con punto (lo que envía un input numérico).
     */
    public const REGLA = 'regex:/^\d{1,12}(\.\d{1,2})?$/';

    /**
     * "1200.5" → 120050, "-3" → -300. Acepta signo y hasta dos decimales.
     *
     * @throws InvalidArgumentException
     */
    public static function aCentavos(string $importe): int
    {
        if (! self::esValido($importe)) {
            throw new InvalidArgumentException("Importe inválido: {$importe}");
        }

        preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', trim($importe), $partes);
        $centavos = (int) $partes[2] * 100 + (int) str_pad($partes[3] ?? '', 2, '0');

        return $partes[1] === '-' ? -$centavos : $centavos;
    }

    /**
     * 120050 → "1200.50".
     */
    public static function aDecimal(int $centavos): string
    {
        $signo = $centavos < 0 ? '-' : '';
        $absoluto = abs($centavos);

        return $signo.intdiv($absoluto, 100).'.'.str_pad((string) ($absoluto % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Decimal con signo opcional y hasta dos decimales, hasta 12 dígitos enteros (decimal(14,2)).
     */
    public static function esValido(string $importe): bool
    {
        return preg_match('/^-?\d{1,12}(?:\.\d{1,2})?$/', trim($importe)) === 1;
    }

    /**
     * Normaliza a dos decimales: "1200.5" → "1200.50".
     */
    public static function normalizar(string $importe): string
    {
        return self::aDecimal(self::aCentavos($importe));
    }
}
