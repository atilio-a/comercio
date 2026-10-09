<?php

namespace Tests\Feature\Catalogo;

use App\Enums\Rol;
use App\Models\Producto;
use App\Models\ProductoFoto;
use App\Models\User;
use App\Models\Variante;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MiniaturasListadosTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->gerente = User::factory()->conSucursal(rol: Rol::Gerente)->create();
    }

    public function test_product_list_shows_the_main_thumbnail_or_a_placeholder(): void
    {
        $conFoto = Producto::factory()->create(['nombre' => 'Bota Ana']);
        $foto = ProductoFoto::factory()->for($conFoto)->principal()->create();
        Producto::factory()->create(['nombre' => 'Sandalia Sol']);

        $this->actingAs($this->gerente)->get(route('catalogo.productos.index'))
            ->assertOk()
            ->assertSee(route('catalogo.fotos.mostrar', [$foto, 'miniatura']))
            ->assertSee('alt="Bota Ana"', false)
            ->assertSee('aria-label="Sin imagen de Sandalia Sol"', false);
    }

    public function test_stock_list_shows_the_main_thumbnail(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Bota Ana']);
        $foto = ProductoFoto::factory()->for($producto)->principal()->create();
        Variante::factory()->for($producto)->create();

        $this->actingAs($this->gerente)->get(route('stock.index'))
            ->assertOk()
            ->assertSee(route('catalogo.fotos.mostrar', [$foto, 'miniatura']));
    }

    public function test_listings_do_not_run_one_query_per_product(): void
    {
        // The first request of a session sets the active branch and warms caches.
        $this->actingAs($this->gerente)->get('/dashboard')->assertOk();

        $this->crearProductosConFoto(2);
        $productosPocos = $this->contarConsultas(route('catalogo.productos.index'));
        $stockPocos = $this->contarConsultas(route('stock.index'));

        $this->crearProductosConFoto(5);
        $productosMuchos = $this->contarConsultas(route('catalogo.productos.index'));
        $stockMuchos = $this->contarConsultas(route('stock.index'));

        $this->assertSame($productosPocos, $productosMuchos);
        $this->assertSame($stockPocos, $stockMuchos);
    }

    private function crearProductosConFoto(int $cantidad): void
    {
        Producto::factory()->count($cantidad)->create()->each(function (Producto $producto) {
            ProductoFoto::factory()->for($producto)->principal()->create();
            Variante::factory()->for($producto)->create();
        });
    }

    private function contarConsultas(string $url): int
    {
        $this->actingAs($this->gerente);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get($url)->assertOk();

        $cantidad = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $cantidad;
    }
}
