<?php

namespace Tests\Feature\Catalogo;

use App\Models\Color;
use App\Models\Producto;
use App\Models\Variante;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VarianteModeloTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_variants_with_the_same_product_color_and_size_are_rejected(): void
    {
        $producto = Producto::factory()->create();
        $negro = Color::factory()->create(['nombre' => 'Negro']);
        Variante::factory()->for($producto)->for($negro)->create(['talle' => '37']);

        $this->expectException(UniqueConstraintViolationException::class);

        Variante::factory()->for($producto)->for($negro)->create(['talle' => '37']);
    }

    public function test_two_variants_without_size_and_the_same_color_are_rejected(): void
    {
        $producto = Producto::factory()->create();
        $azul = Color::factory()->create(['nombre' => 'Azul']);
        Variante::factory()->for($producto)->for($azul)->create(['talle' => null]);

        $this->expectException(UniqueConstraintViolationException::class);

        Variante::factory()->for($producto)->for($azul)->create(['talle' => null]);
    }

    public function test_variant_without_size_and_with_size_can_coexist(): void
    {
        $producto = Producto::factory()->create();
        $azul = Color::factory()->create();
        Variante::factory()->for($producto)->for($azul)->create(['talle' => null]);
        Variante::factory()->for($producto)->for($azul)->create(['talle' => '38']);

        $this->assertSame(2, $producto->variantes()->count());
    }

    public function test_sellable_variants_require_active_variant_and_active_product(): void
    {
        $activa = Variante::factory()->create();
        Variante::factory()->inactivo()->create();
        $deProductoInactivo = Variante::factory()->for(Producto::factory()->inactivo())->create();

        $this->assertSame([$activa->id], Variante::vendibles()->pluck('id')->all());
        $this->assertTrue($deProductoInactivo->fresh()->activo);
    }

    public function test_amounts_are_kept_as_two_decimal_strings(): void
    {
        $variante = Variante::factory()->create(['costo' => 40000, 'precio_final' => '89900.5']);

        $this->assertSame('40000.00', $variante->fresh()->costo);
        $this->assertSame('89900.50', $variante->fresh()->precio_final);
    }

    public function test_description_shows_size_only_when_present(): void
    {
        $negro = Color::factory()->create(['nombre' => 'Negro']);

        $this->assertSame('Negro / 37', Variante::factory()->for($negro)->create(['talle' => '37'])->descripcion());
        $this->assertSame('Negro', Variante::factory()->for($negro)->create(['talle' => null])->descripcion());
    }
}
