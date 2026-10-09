<?php

namespace Tests\Unit\Catalogo;

use App\Services\GeneradorVariantes;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParserTallesTest extends TestCase
{
    public function test_commas_and_numeric_ranges_are_expanded(): void
    {
        $this->assertSame(['35', '36', '37', '38', '40'], GeneradorVariantes::parsearTalles('35-38, 40'));
    }

    public function test_alphanumeric_sizes_are_accepted_and_uppercased(): void
    {
        $this->assertSame(['S', 'M', 'XL', '37.5', '2/3'], GeneradorVariantes::parsearTalles('s, M ,xl, 37.5, 2/3'));
    }

    public function test_repeated_sizes_are_removed_and_blanks_ignored(): void
    {
        $this->assertSame(['36', '37', '38'], GeneradorVariantes::parsearTalles('36-37, , 37, 38,'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidos(): array
    {
        return [
            'rango invertido' => ['40-35'],
            'rango de más de 30' => ['1-31'],
            'símbolos' => ['37; 38'],
            'demasiado largo' => ['TALLEMUYLARGO'],
        ];
    }

    #[DataProvider('invalidos')]
    public function test_invalid_input_is_rejected(string $entrada): void
    {
        $this->expectException(InvalidArgumentException::class);

        GeneradorVariantes::parsearTalles($entrada);
    }

    public function test_a_range_of_exactly_30_is_accepted(): void
    {
        $this->assertCount(30, GeneradorVariantes::parsearTalles('1-30'));
    }

    public function test_combination_count(): void
    {
        $this->assertSame(6, GeneradorVariantes::cantidad(2, ['35', '36', '37']));
        $this->assertSame(2, GeneradorVariantes::cantidad(2, null));
        $this->assertSame(0, GeneradorVariantes::cantidad(0, ['35']));
    }
}
