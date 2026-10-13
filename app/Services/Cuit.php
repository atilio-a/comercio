<?php

namespace App\Services;

/**
 * CUIT/CUIL argentino: normalización, validación del dígito verificador (módulo 11)
 * y formato para mostrar.
 */
class Cuit
{
    /**
     * Prefijos admitidos: personas (20, 23, 24, 25, 26, 27) y empresas (30, 33, 34).
     */
    public const PREFIJOS = ['20', '23', '24', '25', '26', '27', '30', '33', '34'];

    private const PESOS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    /**
     * Quita guiones, puntos y espacios: "30-71234567-1" → "30712345671".
     */
    public static function normalizar(string $cuit): string
    {
        return preg_replace('/[\s.\-]/', '', $cuit) ?? $cuit;
    }

    /**
     * Once dígitos, prefijo admitido y dígito verificador correcto. Acepta guiones.
     */
    public static function esValido(string $cuit): bool
    {
        $cuit = self::normalizar($cuit);

        if (preg_match('/^\d{11}$/', $cuit) !== 1 || ! in_array(substr($cuit, 0, 2), self::PREFIJOS, true)) {
            return false;
        }

        return self::digitoVerificador(substr($cuit, 0, 10)) === (int) $cuit[10];
    }

    /**
     * Dígito verificador de los diez primeros dígitos, o null si el cálculo da 10
     * (en ese caso AFIP asigna otro prefijo y el número no es válido).
     */
    public static function digitoVerificador(string $diezDigitos): ?int
    {
        $suma = 0;

        foreach (self::PESOS as $posicion => $peso) {
            $suma += (int) $diezDigitos[$posicion] * $peso;
        }

        $digito = 11 - ($suma % 11);

        return match ($digito) {
            11 => 0,
            10 => null,
            default => $digito,
        };
    }

    /**
     * "30712345671" → "30-71234567-1". Devuelve el valor tal cual si no tiene 11 dígitos.
     */
    public static function formatear(?string $cuit): string
    {
        if ($cuit === null || preg_match('/^\d{11}$/', $cuit) !== 1) {
            return (string) $cuit;
        }

        return substr($cuit, 0, 2).'-'.substr($cuit, 2, 8).'-'.substr($cuit, 10);
    }
}
