<?php

namespace Tests\Feature\Ventas;

use App\Enums\Parametro;
use App\Models\Color;
use App\Models\Producto;
use App\Models\ProductoFoto;
use App\Models\Variante;
use App\Services\Parametros;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioVentas;
use Tests\TestCase;

class VentaRapidaTest extends TestCase
{
    use EscenarioVentas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararEscenarioVentas();
    }

    public function test_the_quick_sale_screen_preselects_the_seller_and_lists_mediums(): void
    {
        $this->abrirCaja($this->vendedorUser);

        $this->actingAs($this->vendedorUser)->get(route('ventas.create'))
            ->assertOk()
            ->assertSee('Venta rápida')
            ->assertSee('Escanear código (lector)')
            ->assertSee('Valeria Vendedora')
            ->assertSee($this->enConfiguracion('"vendedorId":'.$this->vendedor->id), false)
            ->assertSee($this->enConfiguracion('"usarTalles":true'), false)
            ->assertSee($this->enConfiguracion('"cuotas":3'), false)
            ->assertSee($this->enConfiguracion('"recargo_pct":"10.00"'), false)
            ->assertDontSee('No tenés la caja abierta');
    }

    public function test_the_screen_warns_when_the_cash_register_is_closed_and_the_user_is_not_a_seller(): void
    {
        $this->actingAs($this->cajero)->get(route('ventas.create'))
            ->assertOk()
            ->assertSee('No tenés la caja abierta')
            ->assertSee($this->enConfiguracion('"vendedorId":null'), false);
    }

    public function test_search_groups_variants_by_product_with_price_stock_and_sizes(): void
    {
        $respuesta = $this->actingAs($this->vendedorUser)->getJson(route('ventas.variantes.buscar', ['q' => 'bota']))->assertOk();

        $producto = $respuesta->json('productos.0');
        $this->assertSame('Bota Ana', $producto['nombre']);
        $this->assertTrue($producto['usa_talles']);
        $this->assertSame(['Negro'], array_column($producto['colores'], 'nombre'));
        $this->assertSame(['37', '38'], array_column($producto['variantes'], 'talle'));
        $this->assertSame('12100.00', $producto['variantes'][0]['precio_final']);
        $this->assertSame(5, $producto['variantes'][0]['stock']);
        $this->assertCount(1, $respuesta->json('productos'));
    }

    public function test_sizes_are_hidden_when_the_parameter_is_disabled(): void
    {
        app(Parametros::class)->guardar(Parametro::UsarTalles, false);

        $producto = $this->actingAs($this->vendedorUser)->getJson(route('ventas.variantes.buscar', ['q' => 'bota']))->assertOk()->json('productos.0');

        $this->assertFalse($producto['usa_talles']);
        foreach ($producto['variantes'] as $variante) {
            $this->assertArrayNotHasKey('talle', $variante);
        }

        $this->actingAs($this->vendedorUser)->get(route('ventas.create'))
            ->assertOk()
            ->assertSee($this->enConfiguracion('"usarTalles":false'), false);
    }

    public function test_exact_search_by_barcode_returns_only_that_variant(): void
    {
        $productos = $this->actingAs($this->vendedorUser)
            ->getJson(route('ventas.variantes.buscar', ['q' => '7790000000038', 'exacto' => 1]))
            ->assertOk()
            ->json('productos');

        $this->assertCount(1, $productos);
        $this->assertSame([$this->negro38->id], array_column($productos[0]['variantes'], 'variante_id'));

        $this->assertSame([], $this->actingAs($this->vendedorUser)
            ->getJson(route('ventas.variantes.buscar', ['q' => 'bota', 'exacto' => 1]))
            ->json('productos'));
    }

    public function test_inactive_variants_are_not_offered(): void
    {
        $this->negro38->update(['activo' => false]);

        $variantes = $this->actingAs($this->vendedorUser)->getJson(route('ventas.variantes.buscar', ['q' => 'bota']))->json('productos.0.variantes');

        $this->assertSame([$this->negro37->id], array_column($variantes, 'variante_id'));
        $this->assertSame([], $this->actingAs($this->vendedorUser)
            ->getJson(route('ventas.variantes.buscar', ['q' => '7790000000038', 'exacto' => 1]))
            ->json('productos'));
    }

    public function test_each_color_carries_its_own_photo_or_the_main_one(): void
    {
        $suela = Color::factory()->create(['nombre' => 'Suela']);
        $azul = Color::factory()->create(['nombre' => 'Azul']);
        $producto = $this->negro37->producto;
        Variante::factory()->for($producto)->for($suela)->create(['talle' => '37']);
        Variante::factory()->for($producto)->for($azul)->create(['talle' => '37']);
        $principal = ProductoFoto::factory()->for($producto)->create(['es_principal' => true, 'color_id' => null, 'orden' => 1]);
        $deSuela = ProductoFoto::factory()->for($producto)->create(['es_principal' => false, 'color_id' => $suela->id, 'orden' => 2]);

        $colores = collect($this->actingAs($this->vendedorUser)->getJson(route('ventas.variantes.buscar', ['q' => 'bota']))->json('productos.0.colores'))
            ->keyBy('nombre');

        $this->assertSame(route('catalogo.fotos.mostrar', [$deSuela, 'miniatura']), $colores['Suela']['foto']);
        $this->assertSame(route('catalogo.fotos.mostrar', [$principal, 'miniatura']), $colores['Negro']['foto']);
        $this->assertSame(route('catalogo.fotos.mostrar', [$principal, 'miniatura']), $colores['Azul']['foto']);
    }

    public function test_search_does_not_run_one_query_per_product(): void
    {
        foreach (range(1, 5) as $numero) {
            Variante::factory()->for(Producto::factory()->create(['nombre' => "Bota modelo {$numero}"]))->create();
        }

        $this->actingAs($this->vendedorUser)->getJson(route('ventas.variantes.buscar', ['q' => 'bota']))->assertOk();

        DB::enableQueryLog();
        $this->actingAs($this->vendedorUser)->getJson(route('ventas.variantes.buscar', ['q' => 'bota']))->assertOk()->assertJsonCount(6, 'productos');
        $consultas = count(DB::getQueryLog());

        $this->assertLessThan(20, $consultas);
    }

    /**
     * Js::from() serializes the configuration with escaped quotes inside JSON.parse().
     */
    private function enConfiguracion(string $json): string
    {
        return str_replace('"', chr(92).'u0022', $json);
    }
}
