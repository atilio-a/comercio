<?php

namespace App\Services;

use App\Enums\Parametro;
use App\Models\Color;
use App\Models\Parametro as ParametroGuardado;
use App\Models\Producto;
use App\Models\Variante;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Genera SKU legibles y códigos de barras EAN-13 internos (prefijo 2) para variantes.
 */
class GeneradorCodigos
{
    /**
     * Fila de `parametros` con el último número usado para EAN internos. No es un parámetro
     * editable: no figura en {@see Parametro}.
     */
    public const CLAVE_SECUENCIA_EAN = 'secuencia_ean_interno';

    /**
     * Caracteres permitidos para SKU y códigos de barras ingresados a mano.
     */
    public const PATRON_CODIGO = '/^[A-Za-z0-9._-]{4,50}$/';

    /**
     * "00012-NEG-37"; si ya existe, agrega "-2", "-3"…
     */
    public function sku(Producto $producto, Color $color, ?string $talle): string
    {
        $partes = [
            str_pad((string) $producto->id, 5, '0', STR_PAD_LEFT),
            $this->abreviar($color->nombre),
        ];

        if ($talle !== null && $talle !== '') {
            $partes[] = $this->limpiar($talle);
        }

        $base = Str::limit(implode('-', array_filter($partes)), 44, '');
        $sku = $base;

        for ($sufijo = 2; Variante::where('sku', $sku)->exists(); $sufijo++) {
            $sku = $base.'-'.$sufijo;
        }

        return $sku;
    }

    /**
     * Próximo EAN-13 interno libre. Reserva el número con un bloqueo sobre la secuencia,
     * por eso debe llamarse dentro de una transacción.
     */
    public function codigoBarras(): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('El código de barras interno se genera dentro de una transacción.');
        }

        ParametroGuardado::query()->insertOrIgnore([
            'clave' => self::CLAVE_SECUENCIA_EAN,
            'valor' => '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secuencia = ParametroGuardado::where('clave', self::CLAVE_SECUENCIA_EAN)->lockForUpdate()->sole();
        $numero = (int) $secuencia->valor;

        do {
            $numero++;
            $codigo = self::ean13('2'.str_pad((string) $numero, 11, '0', STR_PAD_LEFT));
        } while (Variante::where('codigo_barras', $codigo)->exists());

        $secuencia->update(['valor' => (string) $numero]);

        return $codigo;
    }

    /**
     * Agrega el dígito verificador a 12 dígitos.
     */
    public static function ean13(string $doceDigitos): string
    {
        if (! preg_match('/^\d{12}$/', $doceDigitos)) {
            throw new LogicException('Un EAN-13 se arma con 12 dígitos.');
        }

        $suma = 0;

        foreach (str_split($doceDigitos) as $posicion => $digito) {
            $suma += (int) $digito * ($posicion % 2 === 0 ? 1 : 3);
        }

        return $doceDigitos.((10 - $suma % 10) % 10);
    }

    private function abreviar(string $texto): string
    {
        return Str::limit($this->limpiar($texto), 3, '');
    }

    private function limpiar(string $texto): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9.]/', '', Str::ascii($texto)));
    }
}
