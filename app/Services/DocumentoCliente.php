<?php

namespace App\Services;

/**
 * Documento de un cliente: DNI (7 u 8 dígitos) o CUIT (11 dígitos con dígito verificador).
 * Se guarda solo con dígitos.
 */
class DocumentoCliente
{
    /**
     * Quita puntos, guiones y espacios: "30.123.456" → "30123456".
     */
    public static function normalizar(string $documento): string
    {
        return Cuit::normalizar($documento);
    }

    public static function esValido(string $documento): bool
    {
        $documento = self::normalizar($documento);

        return self::esDni($documento) || Cuit::esValido($documento);
    }

    /**
     * DNI ya normalizado: 7 u 8 dígitos.
     */
    public static function esDni(string $documento): bool
    {
        return preg_match('/^\d{7,8}$/', $documento) === 1;
    }

    /**
     * CUIT ya normalizado y válido.
     */
    public static function esCuit(string $documento): bool
    {
        return strlen($documento) === 11 && Cuit::esValido($documento);
    }

    /**
     * "30123456" → "30.123.456", "20301234567" → "20-30123456-7". Otro valor se devuelve tal cual.
     */
    public static function formatear(?string $documento): string
    {
        if ($documento !== null && self::esDni($documento)) {
            return number_format((int) $documento, 0, ',', '.');
        }

        return Cuit::formatear($documento);
    }
}
