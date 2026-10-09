<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Calcula neto e IVA a partir del precio final con IVA (el precio final es el dato maestro).
 *
 * Trabaja con enteros (centavos y centésimos de punto porcentual) para no arrastrar
 * errores de punto flotante: neto = final / (1 + alícuota), redondeado a centavos,
 * e IVA = final − neto, de modo que neto + IVA siempre da el precio final.
 */
class CalculadoraIva
{
    /**
     * @param  string  $precioFinal  Importe con hasta dos decimales, por ejemplo "12100.00".
     * @param  string  $alicuota  Porcentaje con hasta dos decimales, por ejemplo "21.00".
     * @return array{neto: string, iva: string}
     */
    public function desglosar(string $precioFinal, string $alicuota): array
    {
        $finalCentavos = $this->aCentesimos($precioFinal);
        $alicuotaCentesimos = $this->aCentesimos($alicuota);

        $denominador = 10000 + $alicuotaCentesimos;
        $netoCentavos = $this->dividirRedondeando($finalCentavos * 10000, $denominador);

        return [
            'neto' => $this->aDecimal($netoCentavos),
            'iva' => $this->aDecimal($finalCentavos - $netoCentavos),
        ];
    }

    /**
     * "12100.5" → 1210050. Acepta signo y hasta dos decimales.
     */
    private function aCentesimos(string $valor): int
    {
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', trim($valor), $partes)) {
            throw new InvalidArgumentException("Importe inválido: {$valor}");
        }

        $centesimos = (int) $partes[2] * 100 + (int) str_pad($partes[3] ?? '', 2, '0');

        return $partes[1] === '-' ? -$centesimos : $centesimos;
    }

    private function aDecimal(int $centesimos): string
    {
        $signo = $centesimos < 0 ? '-' : '';
        $absoluto = abs($centesimos);

        return $signo.intdiv($absoluto, 100).'.'.str_pad((string) ($absoluto % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * División entera redondeando la mitad hacia arriba (en valor absoluto).
     */
    private function dividirRedondeando(int $numerador, int $denominador): int
    {
        $signo = $numerador < 0 ? -1 : 1;

        return $signo * intdiv(2 * abs($numerador) + $denominador, 2 * $denominador);
    }
}
