<?php

namespace Tests\Feature\Catalogo;

use App\Enums\Rol;
use App\Models\Color;
use App\Models\Producto;
use App\Models\ProductoFoto;
use App\Models\User;
use App\Models\Variante;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GaleriaVistaTest extends TestCase
{
    use RefreshDatabase;

    private Producto $producto;

    private Color $suela;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->producto = Producto::factory()->create(['nombre' => 'Bota Ana']);
        $this->suela = Color::factory()->create(['nombre' => 'Suela']);
        Variante::factory()->for($this->producto)->for($this->suela)->create();
    }

    public function test_gallery_highlights_the_main_photo_and_shows_alt_texts(): void
    {
        $principal = ProductoFoto::factory()->for($this->producto)->principal()->create(['orden' => 1]);
        $deSuela = ProductoFoto::factory()->for($this->producto)->create(['orden' => 2, 'color_id' => $this->suela->id]);
        $gerente = User::factory()->conSucursal(rol: Rol::Gerente)->create();

        $this->actingAs($gerente)->get(route('catalogo.productos.show', $this->producto))
            ->assertOk()
            ->assertSee('Imágenes (2/10)')
            ->assertSee('Principal')
            ->assertSee(route('catalogo.fotos.mostrar', [$principal, 'miniatura']))
            ->assertSee('alt="Bota Ana"', false)
            ->assertSee('alt="Bota Ana – Suela"', false)
            ->assertSee(route('catalogo.productos.fotos.principal', [$this->producto, $deSuela]))
            ->assertDontSee(route('catalogo.productos.fotos.principal', [$this->producto, $principal]))
            ->assertSee('name="fotos[]"', false);
    }

    public function test_salesperson_sees_the_gallery_without_management_controls(): void
    {
        $foto = ProductoFoto::factory()->for($this->producto)->principal()->create();
        $vendedor = User::factory()->conSucursal(rol: Rol::Vendedor)->create();

        $this->actingAs($vendedor)->get(route('catalogo.productos.show', $this->producto))
            ->assertOk()
            ->assertSee(route('catalogo.fotos.mostrar', [$foto, 'miniatura']))
            ->assertDontSee('name="fotos[]"', false)
            ->assertDontSee(route('catalogo.productos.fotos.destroy', [$this->producto, $foto]));
    }

    public function test_upload_form_is_hidden_when_the_quota_is_full(): void
    {
        ProductoFoto::factory()->count(10)->for($this->producto)->create();
        $gerente = User::factory()->conSucursal(rol: Rol::Gerente)->create();

        $this->actingAs($gerente)->get(route('catalogo.productos.show', $this->producto))
            ->assertOk()
            ->assertSee('Imágenes (10/10)')
            ->assertDontSee('name="fotos[]"', false);
    }

    public function test_product_without_photos_shows_a_message(): void
    {
        $gerente = User::factory()->conSucursal(rol: Rol::Gerente)->create();

        $this->actingAs($gerente)->get(route('catalogo.productos.show', $this->producto))
            ->assertOk()
            ->assertSee('Este producto todavía no tiene imágenes.');
    }
}
