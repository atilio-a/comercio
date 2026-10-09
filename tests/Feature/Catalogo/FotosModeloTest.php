<?php

namespace Tests\Feature\Catalogo;

use App\Models\Color;
use App\Models\Producto;
use App\Models\ProductoFoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FotosModeloTest extends TestCase
{
    use RefreshDatabase;

    public function test_photos_are_ordered_and_the_main_one_is_reachable(): void
    {
        $producto = Producto::factory()->create();
        $segunda = ProductoFoto::factory()->for($producto)->create(['orden' => 2]);
        $primera = ProductoFoto::factory()->for($producto)->principal()->create(['orden' => 1]);

        $this->assertSame([$primera->id, $segunda->id], $producto->fotos->pluck('id')->all());
        $this->assertTrue($producto->fotoPrincipal->is($primera));
    }

    public function test_photo_for_a_color_falls_back_to_the_main_one(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Bota Ana']);
        $suela = Color::factory()->create(['nombre' => 'Suela']);
        $negro = Color::factory()->create(['nombre' => 'Negro']);
        $principal = ProductoFoto::factory()->for($producto)->principal()->create(['orden' => 1]);
        $deSuela = ProductoFoto::factory()->for($producto)->create(['orden' => 2, 'color_id' => $suela->id]);

        $this->assertTrue($producto->fotoPara($suela)->is($deSuela));
        $this->assertTrue($producto->fotoPara($negro)->is($principal));
        $this->assertTrue($producto->fotoPara(null)->is($principal));
        $this->assertSame('Bota Ana – Suela', $deSuela->textoAlternativo());
        $this->assertSame('Bota Ana', $principal->textoAlternativo());
    }

    public function test_product_without_photos_has_no_photo(): void
    {
        $producto = Producto::factory()->create();

        $this->assertNull($producto->fotoPara(Color::factory()->create()));
        $this->assertNull($producto->fotoPrincipal);
    }
}
