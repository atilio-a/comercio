<?php

namespace Tests\Feature\Ventas;

use App\Exceptions\VentaRechazada;
use App\Services\CalculadoraVenta;
use App\Services\Importes;
use Tests\TestCase;

class CalculadoraVentaTest extends TestCase
{
    private CalculadoraVenta $calculadora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculadora = app(CalculadoraVenta::class);
    }

    public function test_a_single_item_is_broken_down_from_its_final_price(): void
    {
        $resultado = $this->calculadora->calcular(
            [$this->linea('12100.00', '21.00')],
            '0',
            [['importe' => '12100.00', 'recargo_pct' => '0']],
        );

        $this->assertSame('12100.00', $resultado['total']);
        $this->assertSame('10000.00', $resultado['subtotal_neto']);
        $this->assertSame('2100.00', $resultado['iva']);
        $this->assertSame([
            'bruto' => '12100.00',
            'precio_neto' => '10000.00',
            'descuento' => '0.00',
            'descuento_venta' => '0.00',
            'recargo' => '0.00',
            'total' => '12100.00',
            'neto' => '10000.00',
            'iva' => '2100.00',
        ], $resultado['lineas'][0]);
    }

    public function test_quantity_multiplies_the_unit_price(): void
    {
        $resultado = $this->calculadora->calcular(
            [$this->linea('50000.00', '21.00', 2)],
            '0',
            [['importe' => '100000.00', 'recargo_pct' => '0']],
        );

        $this->assertSame('100000.00', $resultado['total']);
        $this->assertSame('100000.00', $resultado['lineas'][0]['bruto']);
    }

    public function test_the_general_discount_is_distributed_and_the_last_item_closes_the_cents(): void
    {
        $resultado = $this->calculadora->calcular(
            [$this->linea('100.00'), $this->linea('200.00')],
            '100',
            [['importe' => '200.00', 'recargo_pct' => '0']],
        );

        $this->assertSame('33.33', $resultado['lineas'][0]['descuento_venta']);
        $this->assertSame('66.67', $resultado['lineas'][1]['descuento_venta']);
        $this->assertSame('66.67', $resultado['lineas'][0]['total']);
        $this->assertSame('133.33', $resultado['lineas'][1]['total']);
        $this->assertSame('200.00', $resultado['total']);
        $this->assertSame('100.00', $resultado['descuento']);
        $this->assertSame('100.00', $resultado['descuento_general']);
    }

    public function test_item_and_general_discounts_combine(): void
    {
        $resultado = $this->calculadora->calcular(
            [$this->linea('1000.00', '21.00', 2, '500'), $this->linea('1000.00')],
            '250',
            [['importe' => '2250.00', 'recargo_pct' => '0']],
        );

        $this->assertSame('500.00', $resultado['lineas'][0]['descuento']);
        $this->assertSame('150.00', $resultado['lineas'][0]['descuento_venta']);
        $this->assertSame('1350.00', $resultado['lineas'][0]['total']);
        $this->assertSame('100.00', $resultado['lineas'][1]['descuento_venta']);
        $this->assertSame('900.00', $resultado['lineas'][1]['total']);
        $this->assertSame('3000.00', $resultado['bruto']);
        $this->assertSame('500.00', $resultado['descuento_items']);
        $this->assertSame('750.00', $resultado['descuento']);
        $this->assertSame('2250.00', $resultado['total']);
    }

    public function test_cents_are_closed_on_the_last_item_and_vat_adds_up_exactly(): void
    {
        $resultado = $this->calculadora->calcular(
            [$this->linea('1.00'), $this->linea('1.00'), $this->linea('1.00')],
            '1',
            [['importe' => '2.00', 'recargo_pct' => '0']],
        );

        $this->assertSame(['0.33', '0.33', '0.34'], array_column($resultado['lineas'], 'descuento_venta'));
        $this->assertSame(['0.67', '0.67', '0.66'], array_column($resultado['lineas'], 'total'));
        $this->assertCuadra($resultado);
    }

    public function test_a_discount_never_leaves_a_line_negative(): void
    {
        $resultado = $this->calculadora->calcular(
            [$this->linea('0.03'), $this->linea('0.03'), $this->linea('0.01')],
            '0.06',
            [['importe' => '0.01', 'recargo_pct' => '0']],
        );

        $this->assertSame(['0.02', '0.03', '0.01'], array_column($resultado['lineas'], 'descuento_venta'));
        $this->assertSame(['0.01', '0.00', '0.00'], array_column($resultado['lineas'], 'total'));
        $this->assertSame('0.01', $resultado['total']);
    }

    public function test_the_instalment_surcharge_joins_the_taxable_total_with_each_items_vat_rate(): void
    {
        $resultado = $this->calculadora->calcular(
            [$this->linea('12100.00', '21.00'), $this->linea('11050.00', '10.50')],
            '0',
            [['importe' => '23150.00', 'recargo_pct' => '10.00']],
        );

        $this->assertSame('2315.00', $resultado['pagos'][0]['recargo']);
        $this->assertSame('25465.00', $resultado['pagos'][0]['cobrado']);
        $this->assertSame('2315.00', $resultado['recargo']);
        $this->assertSame('25465.00', $resultado['total']);
        $this->assertSame('23150.00', $resultado['a_pagar']);

        $this->assertSame('1210.00', $resultado['lineas'][0]['recargo']);
        $this->assertSame('13310.00', $resultado['lineas'][0]['total']);
        $this->assertSame('11000.00', $resultado['lineas'][0]['neto']);
        $this->assertSame('2310.00', $resultado['lineas'][0]['iva']);

        $this->assertSame('1105.00', $resultado['lineas'][1]['recargo']);
        $this->assertSame('12155.00', $resultado['lineas'][1]['total']);
        $this->assertSame('11000.00', $resultado['lineas'][1]['neto']);
        $this->assertSame('1155.00', $resultado['lineas'][1]['iva']);

        $this->assertSame('22000.00', $resultado['subtotal_neto']);
        $this->assertSame('3465.00', $resultado['iva']);
        $this->assertCuadra($resultado);
    }

    public function test_the_surcharge_applies_only_to_the_part_paid_in_instalments(): void
    {
        $resultado = $this->calculadora->calcular(
            [$this->linea('20000.00')],
            '0',
            [
                ['importe' => '10000.00', 'recargo_pct' => '0'],
                ['importe' => '10000.00', 'recargo_pct' => '10.00'],
            ],
        );

        $this->assertSame('0.00', $resultado['pagos'][0]['recargo']);
        $this->assertSame('1000.00', $resultado['pagos'][1]['recargo']);
        $this->assertSame('21000.00', $resultado['total']);
    }

    public function test_the_surcharge_is_rounded_half_up_to_cents(): void
    {
        $this->assertSame(106, $this->calculadora->recargo(1005, '10.50'));
        $this->assertSame(105, $this->calculadora->recargo(1004, '10.50'));
        $this->assertSame(0, $this->calculadora->recargo(1005, '0'));
    }

    public function test_odd_surcharges_still_add_up_with_mixed_rates(): void
    {
        $resultado = $this->calculadora->calcular(
            [$this->linea('999.99', '21.00', 3), $this->linea('1234.57', '10.50'), $this->linea('0.01', '27.00')],
            '33.33',
            [['importe' => '1000.00', 'recargo_pct' => '0'], ['importe' => '3201.22', 'recargo_pct' => '17.35']],
        );

        $this->assertSame('555.41', $resultado['recargo']);
        $this->assertSame('4756.63', $resultado['total']);
        $this->assertSame('555.41', Importes::aDecimal(array_sum(array_map(fn (array $linea) => Importes::aCentavos($linea['recargo']), $resultado['lineas']))));
        $this->assertCuadra($resultado);
    }

    public function test_an_item_discount_larger_than_the_item_is_rejected(): void
    {
        $this->assertRechazo('items.1.descuento', fn () => $this->calculadora->calcular(
            [$this->linea('1000.00'), $this->linea('5000.00', '21.00', 1, '6000')],
            '0',
            [],
            false,
        ));
    }

    public function test_a_general_discount_larger_than_the_subtotal_is_rejected(): void
    {
        $this->assertRechazo('descuento', fn () => $this->calculadora->calcular([$this->linea('1000.00')], '1000.01', [], false));
    }

    public function test_a_zero_total_is_rejected(): void
    {
        $this->assertRechazo('descuento', fn () => $this->calculadora->calcular([$this->linea('1000.00')], '1000', [], false));
    }

    public function test_a_sale_without_items_is_rejected(): void
    {
        $this->assertRechazo('items', fn () => $this->calculadora->calcular([], '0', [], false));
    }

    public function test_payments_must_add_up_to_the_total(): void
    {
        $lineas = [$this->linea('30000.00')];

        $excepcion = $this->assertRechazo('pagos', fn () => $this->calculadora->calcular($lineas, '0', [
            ['importe' => '10000', 'recargo_pct' => '0'],
            ['importe' => '15000', 'recargo_pct' => '0'],
        ]));
        $this->assertSame('Los pagos suman $ 25.000,00 y el total es $ 30.000,00: faltan $ 5.000,00.', $excepcion->getMessage());

        $excepcion = $this->assertRechazo('pagos', fn () => $this->calculadora->calcular($lineas, '0', [['importe' => '30000.01', 'recargo_pct' => '0']]));
        $this->assertStringContainsString('sobran $ 0,01', $excepcion->getMessage());

        $this->assertRechazo('pagos', fn () => $this->calculadora->calcular($lineas, '0', []));
        $this->assertRechazo('pagos.1.importe', fn () => $this->calculadora->calcular($lineas, '0', [
            ['importe' => '30000', 'recargo_pct' => '0'],
            ['importe' => '0', 'recargo_pct' => '0'],
        ]));
    }

    public function test_the_preview_reports_what_is_pending_instead_of_rejecting(): void
    {
        $resultado = $this->calculadora->calcular(
            [$this->linea('30000.00')],
            '0',
            [['importe' => '10000', 'recargo_pct' => '10']],
            false,
        );

        $this->assertSame('20000.00', $resultado['pendiente']);
        $this->assertSame('10000.00', $resultado['pagado']);
        $this->assertSame('1000.00', $resultado['recargo']);
        $this->assertSame('31000.00', $resultado['total']);
    }

    /**
     * @return array{precio_final: string, alicuota: string, cantidad: int, descuento: string}
     */
    private function linea(string $precio, string $alicuota = '21.00', int $cantidad = 1, string $descuento = '0'): array
    {
        return ['precio_final' => $precio, 'alicuota' => $alicuota, 'cantidad' => $cantidad, 'descuento' => $descuento];
    }

    /**
     * Neto + IVA = total en cada línea y en la venta; la suma de las líneas da el total.
     *
     * @param  array<string, mixed>  $resultado
     */
    private function assertCuadra(array $resultado): void
    {
        $totales = 0;

        foreach ($resultado['lineas'] as $linea) {
            $this->assertSame(Importes::aCentavos($linea['total']), Importes::aCentavos($linea['neto']) + Importes::aCentavos($linea['iva']));
            $totales += Importes::aCentavos($linea['total']);
        }

        $this->assertSame(Importes::aCentavos($resultado['total']), $totales);
        $this->assertSame(Importes::aCentavos($resultado['total']), Importes::aCentavos($resultado['subtotal_neto']) + Importes::aCentavos($resultado['iva']));
    }

    private function assertRechazo(string $campo, callable $operacion): VentaRechazada
    {
        try {
            $operacion();
        } catch (VentaRechazada $e) {
            $this->assertSame($campo, $e->campo);

            return $e;
        }

        $this->fail('Se esperaba VentaRechazada en '.$campo);
    }
}
