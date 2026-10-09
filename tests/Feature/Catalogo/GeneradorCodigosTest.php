<?php

namespace Tests\Feature\Catalogo;

use App\Models\Color;
use App\Models\Producto;
use App\Models\Variante;
use App\Services\GeneradorCodigos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class GeneradorCodigosTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_digit_is_correct(): void
    {
        $this->assertSame('7791234567898', GeneradorCodigos::ean13('779123456789'));
        $this->assertSame('2000000000015', GeneradorCodigos::ean13('200000000001'));
        $this->assertSame('4006381333931', GeneradorCodigos::ean13('400638133393'));
    }

    public function test_internal_barcodes_start_with_2_and_are_unique_across_calls(): void
    {
        $generador = app(GeneradorCodigos::class);

        $codigos = DB::transaction(fn () => [$generador->codigoBarras(), $generador->codigoBarras()]);
        $otro = DB::transaction(fn () => $generador->codigoBarras());

        $this->assertSame(['2000000000015', '2000000000022'], $codigos);
        $this->assertSame('2000000000039', $otro);
        $this->assertSame(GeneradorCodigos::ean13(substr($otro, 0, 12)), $otro);
    }

    public function test_numbers_taken_by_manual_codes_are_skipped(): void
    {
        Variante::factory()->create(['codigo_barras' => '2000000000015']);

        $codigo = DB::transaction(fn () => app(GeneradorCodigos::class)->codigoBarras());

        $this->assertSame('2000000000022', $codigo);
    }

    public function test_internal_barcodes_require_a_transaction(): void
    {
        // RefreshDatabase wraps each test in a transaction, so simulate level 0.
        DB::partialMock()->shouldReceive('transactionLevel')->andReturn(0);

        $this->expectException(LogicException::class);

        app(GeneradorCodigos::class)->codigoBarras();
    }

    public function test_sku_is_readable_and_unique(): void
    {
        $producto = Producto::factory()->create();
        $negro = Color::factory()->create(['nombre' => 'Negro']);
        $generador = app(GeneradorCodigos::class);
        $id = str_pad((string) $producto->id, 5, '0', STR_PAD_LEFT);

        $sku = $generador->sku($producto, $negro, '37');
        $this->assertSame("{$id}-NEG-37", $sku);
        $this->assertMatchesRegularExpression(GeneradorCodigos::PATRON_CODIGO, $sku);

        Variante::factory()->create(['sku' => $sku]);
        $this->assertSame("{$id}-NEG-37-2", $generador->sku($producto, $negro, '37'));
    }

    public function test_sku_without_size_and_with_accents(): void
    {
        $producto = Producto::factory()->create();
        $color = Color::factory()->create(['nombre' => 'Único']);
        $id = str_pad((string) $producto->id, 5, '0', STR_PAD_LEFT);

        $this->assertSame("{$id}-UNI", app(GeneradorCodigos::class)->sku($producto, $color, null));
    }
}
