<?php

namespace Tests\Unit\Catalogo;

use App\Services\CalculadoraIva;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CalculadoraIvaTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function casos(): array
    {
        return [
            '21 % exacto' => ['12100.00', '21.00', '10000.00', '2100.00'],
            '21 % con redondeo' => ['100.00', '21.00', '82.64', '17.36'],
            '0 %' => ['5000.00', '0.00', '5000.00', '0.00'],
            '10,5 %' => ['1105.00', '10.50', '1000.00', '105.00'],
            '27 %' => ['127.00', '27.00', '100.00', '27.00'],
            'un centavo' => ['0.01', '21.00', '0.01', '0.00'],
            'sin decimales en la entrada' => ['89900', '21', '74297.52', '15602.48'],
        ];
    }

    #[DataProvider('casos')]
    public function test_net_and_vat_are_derived_from_the_final_price(string $final, string $alicuota, string $neto, string $iva): void
    {
        $this->assertSame(['neto' => $neto, 'iva' => $iva], (new CalculadoraIva)->desglosar($final, $alicuota));
    }

    public function test_net_plus_vat_always_equals_the_final_price(): void
    {
        $calculadora = new CalculadoraIva;

        foreach (['21.00', '10.50', '27.00', '0.00', '2.50'] as $alicuota) {
            for ($centavos = 1; $centavos <= 200000; $centavos += 997) {
                $final = sprintf('%d.%02d', intdiv($centavos, 100), $centavos % 100);
                $resultado = $calculadora->desglosar($final, $alicuota);

                $suma = (int) str_replace('.', '', $resultado['neto']) + (int) str_replace('.', '', $resultado['iva']);
                $this->assertSame($centavos, $suma, "{$final} al {$alicuota}");
            }
        }
    }

    public function test_invalid_amounts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CalculadoraIva)->desglosar('12,50', '21.00');
    }
}
