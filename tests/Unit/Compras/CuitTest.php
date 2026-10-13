<?php

namespace Tests\Unit\Compras;

use App\Services\Cuit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CuitTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function validos(): array
    {
        return [
            'empresa con guiones' => ['30-71234567-1'],
            'persona sin guiones' => ['20123456786'],
            'con espacios' => [' 20 12345678 6 '],
            'dígito 0 por resto 0' => ['20400000000'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidos(): array
    {
        return [
            'dígito incorrecto' => ['30-71234567-2'],
            'prefijo inexistente' => ['11-71234567-1'],
            'corto' => ['1234'],
            'largo' => ['307123456711'],
            'letras' => ['30-7123456A-1'],
            'vacío' => [''],
        ];
    }

    #[DataProvider('validos')]
    public function test_valid_cuits(string $cuit): void
    {
        $this->assertTrue(Cuit::esValido($cuit));
    }

    #[DataProvider('invalidos')]
    public function test_invalid_cuits(string $cuit): void
    {
        $this->assertFalse(Cuit::esValido($cuit));
    }

    public function test_a_check_digit_of_ten_is_never_valid(): void
    {
        // 20-00000001-? → sum 2*5 + 1*2 = 12, 11 - (12 % 11) = 10.
        $this->assertNull(Cuit::digitoVerificador('2000000001'));

        foreach (range(0, 9) as $digito) {
            $this->assertFalse(Cuit::esValido('2000000001'.$digito));
        }
    }

    public function test_normalizes_and_formats(): void
    {
        $this->assertSame('30712345671', Cuit::normalizar('30-71234567-1'));
        $this->assertSame('30-71234567-1', Cuit::formatear('30712345671'));
        $this->assertSame('', Cuit::formatear(null));
    }
}
