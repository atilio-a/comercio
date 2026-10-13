<?php

namespace App\Services;

use App\Exceptions\VentaRechazada;
use Brick\Math\BigInteger;

/**
 * Importes de una venta (VEN-02, VEN-08), en centavos enteros. Es puro: no consulta ni graba.
 * Lo usan el registro de la venta y la previsualización del carrito, así las dos aplican las
 * mismas reglas y el mismo redondeo.
 *
 * 1. Por línea: `bruto = precio final × cantidad`, `subtotal = bruto − descuento propio`.
 * 2. El descuento general se reparte en proporción al subtotal de cada línea; los centavos de
 *    redondeo los cierra la última línea con importe.
 * 3. Cada pago con plan de cuotas lleva `recargo = importe × recargo_pct / 100` (redondeo a
 *    centavos, la mitad hacia arriba). El recargo total se reparte entre las líneas igual que
 *    el descuento general: integra el total gravado de cada una (doc 05).
 * 4. `total de línea = subtotal − descuento general + recargo`; neto e IVA salen de ese total
 *    con {@see CalculadoraIva}, así `neto + iva = total` en cada línea y en la venta.
 * 5. Los importes de los pagos (sin recargo) deben sumar exactamente el total sin recargo.
 */
class CalculadoraVenta
{
    public function __construct(private CalculadoraIva $iva) {}

    /**
     * @param  array<int|string, array{precio_final: string, alicuota: string, cantidad: int, descuento: string}>  $lineas  Clave = posición en el formulario.
     * @param  array<int|string, array{importe: string, recargo_pct: string}>  $pagos  Clave = posición en el formulario.
     * @param  bool  $exigirPagos  False en la previsualización: informa lo pendiente en vez de rechazar.
     * @return array{
     *     lineas: array<int|string, array{bruto: string, precio_neto: string, descuento: string, descuento_venta: string, recargo: string, total: string, neto: string, iva: string}>,
     *     pagos: array<int|string, array{importe: string, recargo: string, cobrado: string}>,
     *     bruto: string, descuento_items: string, descuento_general: string, descuento: string,
     *     a_pagar: string, recargo: string, total: string, subtotal_neto: string, iva: string,
     *     pagado: string, pendiente: string
     * }
     *
     * @throws VentaRechazada
     */
    public function calcular(array $lineas, string $descuentoGeneral, array $pagos, bool $exigirPagos = true): array
    {
        if ($lineas === []) {
            throw new VentaRechazada('Agregá al menos un producto.', 'items');
        }

        $subtotales = [];
        $brutos = [];
        $descuentos = [];

        foreach ($lineas as $posicion => $linea) {
            $bruto = Importes::aCentavos($linea['precio_final']) * $linea['cantidad'];
            $descuento = Importes::aCentavos($linea['descuento']);

            if ($descuento < 0 || $descuento > $bruto) {
                throw new VentaRechazada('El descuento no puede superar el importe del ítem ($ '.Formato::importe(Importes::aDecimal($bruto)).').', "items.{$posicion}.descuento");
            }

            $brutos[$posicion] = $bruto;
            $descuentos[$posicion] = $descuento;
            $subtotales[$posicion] = $bruto - $descuento;
        }

        $subtotal = array_sum($subtotales);
        $general = Importes::aCentavos($descuentoGeneral);

        if ($general < 0 || $general > $subtotal) {
            throw new VentaRechazada('El descuento general no puede superar el subtotal ($ '.Formato::importe(Importes::aDecimal($subtotal)).').', 'descuento');
        }

        $aPagar = $subtotal - $general;

        if ($aPagar <= 0) {
            throw new VentaRechazada('El total de la venta debe ser mayor que 0.', 'descuento');
        }

        $descuentosVenta = $this->distribuir($general, $subtotales, limitarABase: true);

        [$resultadoPagos, $pagado, $recargoTotal] = $this->calcularPagos($pagos);

        if ($exigirPagos) {
            $this->verificarPagos($pagos, $pagado, $aPagar);
        }

        $baseRecargo = [];

        foreach ($subtotales as $posicion => $importe) {
            $baseRecargo[$posicion] = $importe - $descuentosVenta[$posicion];
        }

        $recargos = $this->distribuir($recargoTotal, $baseRecargo, limitarABase: false);

        $resultadoLineas = [];
        $netoTotal = 0;
        $ivaTotal = 0;

        foreach ($lineas as $posicion => $linea) {
            $total = $baseRecargo[$posicion] + $recargos[$posicion];
            $desglose = $this->iva->desglosar(Importes::aDecimal($total), $linea['alicuota']);
            $netoTotal += Importes::aCentavos($desglose['neto']);
            $ivaTotal += Importes::aCentavos($desglose['iva']);

            $resultadoLineas[$posicion] = [
                'bruto' => Importes::aDecimal($brutos[$posicion]),
                'precio_neto' => $this->iva->desglosar(Importes::normalizar($linea['precio_final']), $linea['alicuota'])['neto'],
                'descuento' => Importes::aDecimal($descuentos[$posicion]),
                'descuento_venta' => Importes::aDecimal($descuentosVenta[$posicion]),
                'recargo' => Importes::aDecimal($recargos[$posicion]),
                'total' => Importes::aDecimal($total),
                'neto' => $desglose['neto'],
                'iva' => $desglose['iva'],
            ];
        }

        return [
            'lineas' => $resultadoLineas,
            'pagos' => $resultadoPagos,
            'bruto' => Importes::aDecimal(array_sum($brutos)),
            'descuento_items' => Importes::aDecimal(array_sum($descuentos)),
            'descuento_general' => Importes::aDecimal($general),
            'descuento' => Importes::aDecimal(array_sum($descuentos) + $general),
            'a_pagar' => Importes::aDecimal($aPagar),
            'recargo' => Importes::aDecimal($recargoTotal),
            'total' => Importes::aDecimal($aPagar + $recargoTotal),
            'subtotal_neto' => Importes::aDecimal($netoTotal),
            'iva' => Importes::aDecimal($ivaTotal),
            'pagado' => Importes::aDecimal($pagado),
            'pendiente' => Importes::aDecimal($aPagar - $pagado),
        ];
    }

    /**
     * Recargo de un importe con un porcentaje, en centavos: redondeo a centavos, la mitad hacia arriba.
     */
    public function recargo(int $importeCentavos, string $porcentaje): int
    {
        $centesimos = Importes::aCentavos($porcentaje);

        if ($importeCentavos <= 0 || $centesimos <= 0) {
            return 0;
        }

        return intdiv(2 * $importeCentavos * $centesimos + 10000, 20000);
    }

    /**
     * @param  array<int|string, array{importe: string, recargo_pct: string}>  $pagos
     * @return array{0: array<int|string, array{importe: string, recargo: string, cobrado: string}>, 1: int, 2: int}
     */
    private function calcularPagos(array $pagos): array
    {
        $resultado = [];
        $pagado = 0;
        $recargoTotal = 0;

        foreach ($pagos as $posicion => $pago) {
            $importe = max(0, Importes::aCentavos($pago['importe']));
            $recargo = $this->recargo($importe, $pago['recargo_pct']);
            $pagado += $importe;
            $recargoTotal += $recargo;

            $resultado[$posicion] = [
                'importe' => Importes::aDecimal($importe),
                'recargo' => Importes::aDecimal($recargo),
                'cobrado' => Importes::aDecimal($importe + $recargo),
            ];
        }

        return [$resultado, $pagado, $recargoTotal];
    }

    /**
     * @param  array<int|string, array{importe: string, recargo_pct: string}>  $pagos
     *
     * @throws VentaRechazada
     */
    private function verificarPagos(array $pagos, int $pagado, int $aPagar): void
    {
        if ($pagos === []) {
            throw new VentaRechazada('Agregá al menos un pago.', 'pagos');
        }

        foreach ($pagos as $posicion => $pago) {
            if (Importes::aCentavos($pago['importe']) <= 0) {
                throw new VentaRechazada('El importe del pago debe ser mayor que 0.', "pagos.{$posicion}.importe");
            }
        }

        if ($pagado !== $aPagar) {
            $diferencia = Importes::aDecimal(abs($aPagar - $pagado));

            throw new VentaRechazada(sprintf(
                'Los pagos suman $ %s y el total es $ %s: %s $ %s.',
                Formato::importe(Importes::aDecimal($pagado)),
                Formato::importe(Importes::aDecimal($aPagar)),
                $pagado < $aPagar ? 'faltan' : 'sobran',
                Formato::importe($diferencia),
            ), 'pagos');
        }
    }

    /**
     * Reparte un monto en proporción a las bases, con división entera hacia abajo; la última
     * base mayor que 0 recibe el resto. Con `limitarABase`, ninguna parte supera su base (un
     * descuento no deja una línea en negativo): el excedente pasa a las líneas anteriores.
     *
     * @param  array<int|string, int>  $bases
     * @return array<int|string, int>
     */
    private function distribuir(int $monto, array $bases, bool $limitarABase): array
    {
        $partes = array_map(fn () => 0, $bases);
        $total = array_sum($bases);

        if ($monto === 0 || $total <= 0) {
            return $partes;
        }

        $conBase = array_keys(array_filter($bases, fn (int $base) => $base > 0));
        $ultima = end($conBase);

        foreach ($conBase as $posicion) {
            if ($posicion !== $ultima) {
                // Arbitrary precision: monto × base can exceed 64 bits on large sales.
                $partes[$posicion] = BigInteger::of($monto)->multipliedBy($bases[$posicion])->quotient($total)->toInt();
            }
        }

        $partes[$ultima] = $monto - array_sum($partes);

        if ($limitarABase && $partes[$ultima] > $bases[$ultima]) {
            $excedente = $partes[$ultima] - $bases[$ultima];
            $partes[$ultima] = $bases[$ultima];

            foreach (array_reverse($conBase) as $posicion) {
                $lugar = min($excedente, $bases[$posicion] - $partes[$posicion]);
                $partes[$posicion] += $lugar;
                $excedente -= $lugar;
            }
        }

        return $partes;
    }
}
