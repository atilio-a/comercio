<?php

namespace Tests\Feature\Catalogo;

use App\Enums\Parametro;
use App\Enums\Rol;
use App\Models\AlicuotaIva;
use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\Color;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\User;
use App\Models\Variante;
use App\Services\GeneradorCodigos;
use App\Services\Parametros;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductosTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    private Categoria $botas;

    private AlicuotaIva $iva21;

    private Color $negro;

    private Color $suela;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->gerente = User::factory()->conSucursal(rol: Rol::Gerente)->create();
        $this->botas = Categoria::create(['nombre' => 'Botas']);
        $this->iva21 = AlicuotaIva::create(['nombre' => 'IVA 21 %', 'porcentaje' => '21']);
        $this->negro = Color::create(['nombre' => 'Negro']);
        $this->suela = Color::create(['nombre' => 'Suela']);
    }

    public function test_creating_a_product_generates_one_variant_per_color_and_size(): void
    {
        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.store'), $this->datos())
            ->assertSessionHasNoErrors();

        $producto = Producto::where('nombre', 'Bota Ana')->sole();
        $this->assertSame(6, $producto->variantes()->count());
        $this->assertEqualsCanonicalizing(['35', '36', '37'], $producto->variantes()->distinct()->pluck('talle')->all());

        foreach ($producto->variantes as $variante) {
            $this->assertSame('40000.00', $variante->costo);
            $this->assertSame('89900.00', $variante->precio_final);
            $this->assertMatchesRegularExpression('/^2\d{12}$/', $variante->codigo_barras);
            $this->assertSame(GeneradorCodigos::ean13(substr($variante->codigo_barras, 0, 12)), $variante->codigo_barras);
            $this->assertMatchesRegularExpression(GeneradorCodigos::PATRON_CODIGO, $variante->sku);
        }

        $this->assertSame(6, Variante::distinct()->count('codigo_barras'));
        $this->assertSame(6, Variante::distinct()->count('sku'));
    }

    public function test_product_creation_is_audited_once_with_its_variants(): void
    {
        $this->actingAs($this->gerente)->post(route('catalogo.productos.store'), $this->datos());

        $registro = Auditoria::sole();
        $this->assertSame('producto.creado', $registro->accion);
        $this->assertSame('89900', $registro->despues['precio_final']);
        $this->assertCount(6, $registro->despues['variantes']);
        $this->assertContains('Negro / 37', $registro->despues['variantes']);
    }

    public function test_with_sizes_disabled_one_variant_per_color_is_created_without_size(): void
    {
        app(Parametros::class)->guardar(Parametro::UsarTalles, false);

        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.store'), $this->datos(['nombre' => 'Taza Flores', 'talles' => null]))
            ->assertSessionHasNoErrors();

        $producto = Producto::where('nombre', 'Taza Flores')->sole();
        $this->assertSame(2, $producto->variantes()->count());
        $this->assertSame(0, $producto->variantes()->whereNotNull('talle')->count());
    }

    public function test_with_sizes_disabled_sending_sizes_is_rejected(): void
    {
        app(Parametros::class)->guardar(Parametro::UsarTalles, false);

        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.store'), $this->datos())
            ->assertSessionHasErrors(['talles' => 'Los talles están deshabilitados en los parámetros del sistema.']);
    }

    public function test_with_sizes_enabled_sizes_are_required(): void
    {
        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.store'), $this->datos(['talles' => '']))
            ->assertSessionHasErrors('talles');

        $this->assertSame(0, Producto::count());
    }

    public function test_grid_larger_than_200_is_rejected(): void
    {
        $colores = Color::factory()->count(8)->create()->modelKeys();

        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.store'), $this->datos(['colores' => $colores, 'talles' => '1-26']))
            ->assertSessionHasErrors(['colores' => 'La grilla genera 208 variantes; el máximo es 200.']);

        $this->assertSame(0, Producto::count());
    }

    public function test_product_without_colors_is_rejected(): void
    {
        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.store'), $this->datos(['colores' => []]))
            ->assertSessionHasErrors(['colores' => 'Elegí al menos un color: el producto debe tener al menos una variante.']);
    }

    public function test_category_is_required_but_brand_and_season_are_optional(): void
    {
        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.store'), $this->datos(['categoria_id' => null]))
            ->assertSessionHasErrors(['categoria_id' => 'La categoría es obligatoria.']);

        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.store'), $this->datos(['marca_id' => null, 'temporada_id' => null]))
            ->assertSessionHasNoErrors();
    }

    public function test_inactive_options_cannot_be_used_for_new_products(): void
    {
        $this->botas->update(['activo' => false]);

        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.store'), $this->datos())
            ->assertSessionHasErrors('categoria_id');
    }

    public function test_invalid_size_ranges_are_rejected_with_a_message(): void
    {
        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.store'), $this->datos(['talles' => '40-35']))
            ->assertSessionHasErrors('talles');
    }

    public function test_product_page_shows_net_and_vat_calculated_from_the_final_price(): void
    {
        $producto = $this->crearProducto(['precio_final' => '12100']);

        $this->actingAs($this->gerente)->get(route('catalogo.productos.show', $producto))
            ->assertOk()
            ->assertSee('$ 12.100,00')
            ->assertSee('$ 10.000,00')
            ->assertSee('$ 2.100,00')
            ->assertSee('Negro / 35');
    }

    public function test_variants_keep_showing_their_size_when_sizes_are_disabled(): void
    {
        $producto = $this->crearProducto();
        app(Parametros::class)->guardar(Parametro::UsarTalles, false);

        $this->actingAs($this->gerente)->get(route('catalogo.productos.show', $producto))
            ->assertOk()
            ->assertSee('Negro / 37');

        $this->assertSame(6, $producto->variantes()->whereNotNull('talle')->count());
    }

    public function test_size_field_is_not_rendered_when_sizes_are_disabled(): void
    {
        $producto = $this->crearProducto();

        $this->actingAs($this->gerente)->get(route('catalogo.productos.create'))->assertOk()->assertSee('name="talles"', false);

        app(Parametros::class)->guardar(Parametro::UsarTalles, false);

        $this->actingAs($this->gerente)->get(route('catalogo.productos.create'))->assertOk()->assertDontSee('name="talles"', false);
        $this->actingAs($this->gerente)->get(route('catalogo.productos.variantes.create', $producto))->assertOk()->assertDontSee('name="talles"', false);
        $variante = $producto->variantes()->first();
        $this->actingAs($this->gerente)->get(route('catalogo.productos.variantes.edit', [$producto, $variante]))->assertOk()->assertDontSee('name="talle"', false);
    }

    public function test_deactivating_a_product_is_audited_and_its_variants_stop_being_sellable(): void
    {
        $producto = $this->crearProducto();

        $this->actingAs($this->gerente)
            ->put(route('catalogo.productos.update', $producto), [
                'nombre' => 'Bota Ana',
                'categoria_id' => $this->botas->id,
                'alicuota_iva_id' => $this->iva21->id,
                'activo' => '0',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($producto->fresh()->activo);
        $this->assertSame(6, $producto->variantes()->count());
        $this->assertSame(0, Variante::vendibles()->count());
        $this->assertSame(['activo' => false], Auditoria::where('accion', 'producto.actualizado')->sole()->despues);
    }

    public function test_a_product_keeps_its_inactive_category_when_edited(): void
    {
        $producto = $this->crearProducto();
        $this->botas->update(['activo' => false]);

        $this->actingAs($this->gerente)->get(route('catalogo.productos.edit', $producto))->assertOk()->assertSee('Botas');

        $this->actingAs($this->gerente)
            ->put(route('catalogo.productos.update', $producto), [
                'nombre' => 'Bota Ana II',
                'categoria_id' => $this->botas->id,
                'alicuota_iva_id' => $this->iva21->id,
                'activo' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Bota Ana II', $producto->fresh()->nombre);
    }

    public function test_price_change_is_audited_with_previous_and_new_value(): void
    {
        $producto = $this->crearProducto();
        $variante = $producto->variantes()->where('color_id', $this->negro->id)->where('talle', '37')->sole();

        $this->actingAs($this->gerente)
            ->put(route('catalogo.productos.variantes.update', [$producto, $variante]), $this->datosVariante($variante, ['precio_final' => '94900']))
            ->assertSessionHasNoErrors();

        $this->assertSame('94900.00', $variante->fresh()->precio_final);
        $registro = Auditoria::where('accion', 'variante.actualizada')->sole();
        $this->assertSame(['precio_final' => '89900.00'], $registro->antes);
        $this->assertSame(['precio_final' => '94900.00'], $registro->despues);
    }

    public function test_color_and_size_of_a_variant_cannot_change(): void
    {
        $producto = $this->crearProducto();
        $variante = $producto->variantes()->where('color_id', $this->negro->id)->where('talle', '37')->sole();

        $this->actingAs($this->gerente)
            ->put(route('catalogo.productos.variantes.update', [$producto, $variante]), $this->datosVariante($variante, [
                'talle' => '38',
                'color_id' => $this->suela->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('37', $variante->fresh()->talle);
        $this->assertSame($this->negro->id, $variante->fresh()->color_id);
    }

    public function test_invalid_prices_are_rejected(): void
    {
        $producto = $this->crearProducto();
        $variante = $producto->variantes()->first();

        $this->actingAs($this->gerente)
            ->put(route('catalogo.productos.variantes.update', [$producto, $variante]), $this->datosVariante($variante, ['precio_final' => '0']))
            ->assertSessionHasErrors('precio_final');

        $this->actingAs($this->gerente)
            ->put(route('catalogo.productos.variantes.update', [$producto, $variante]), $this->datosVariante($variante, ['costo' => '-1']))
            ->assertSessionHasErrors('costo');

        $this->assertSame('89900.00', $variante->fresh()->precio_final);
    }

    public function test_factory_barcode_can_be_assigned(): void
    {
        $producto = $this->crearProducto();
        $variante = $producto->variantes()->first();

        $this->actingAs($this->gerente)
            ->put(route('catalogo.productos.variantes.update', [$producto, $variante]), $this->datosVariante($variante, ['codigo_barras' => '7791234567890']))
            ->assertSessionHasNoErrors();

        $this->assertSame('7791234567890', $variante->fresh()->codigo_barras);
    }

    public function test_barcode_used_by_an_inactive_variant_cannot_be_reused(): void
    {
        $producto = $this->crearProducto();
        [$primera, $segunda] = $producto->variantes()->take(2)->get();
        $primera->update(['activo' => false]);

        $this->actingAs($this->gerente)
            ->put(route('catalogo.productos.variantes.update', [$producto, $segunda]), $this->datosVariante($segunda, ['codigo_barras' => $primera->codigo_barras]))
            ->assertSessionHasErrors(['codigo_barras' => 'El código de barras ya está en uso.']);
    }

    public function test_codes_with_invalid_characters_are_rejected(): void
    {
        $producto = $this->crearProducto();
        $variante = $producto->variantes()->first();

        $this->actingAs($this->gerente)
            ->put(route('catalogo.productos.variantes.update', [$producto, $variante]), $this->datosVariante($variante, ['sku' => 'AB C']))
            ->assertSessionHasErrors('sku');
    }

    public function test_variants_of_another_product_are_not_reachable(): void
    {
        $producto = $this->crearProducto();
        $otro = Producto::factory()->create();
        $ajena = Variante::factory()->for($otro)->create();

        $this->actingAs($this->gerente)->get(route('catalogo.productos.variantes.edit', [$producto, $ajena]))->assertNotFound();
    }

    public function test_adding_variants_skips_existing_combinations_and_reports_them(): void
    {
        $producto = $this->crearProducto(['colores' => [$this->negro->id]]);

        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.variantes.store', $producto), [
                'colores' => [$this->negro->id],
                'talles' => '37-38',
                'costo' => '40000',
                'precio_final' => '89900',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Se crearon 1 variantes. Ya existían: Negro / 37.');

        $this->assertSame(['35', '36', '37', '38'], $producto->variantes()->orderBy('talle')->pluck('talle')->all());
        $this->assertSame('variante.creada', Auditoria::latest('id')->first()->accion);
    }

    public function test_search_finds_a_product_by_exact_barcode(): void
    {
        $producto = $this->crearProducto();
        Producto::factory()->create(['nombre' => 'Otro producto']);
        $codigo = $producto->variantes()->first()->codigo_barras;

        $this->actingAs($this->gerente)->get(route('catalogo.productos.index', ['q' => $codigo]))
            ->assertOk()
            ->assertViewHas('productos', fn ($productos) => $productos->pluck('id')->all() === [$producto->id]);
    }

    public function test_products_can_be_filtered_by_category_brand_and_state(): void
    {
        $sandalias = Categoria::create(['nombre' => 'Sandalias']);
        $marca = Marca::create(['nombre' => 'Vizzano']);
        $bota = $this->crearProducto();
        $sandalia = Producto::factory()->for($sandalias)->for($marca)->create();
        $inactiva = Producto::factory()->for($sandalias)->inactivo()->create();

        $this->actingAs($this->gerente)->get(route('catalogo.productos.index', ['categoria_id' => $sandalias->id]))
            ->assertViewHas('productos', fn ($productos) => $productos->pluck('id')->all() === [$sandalia->id]);
        $this->actingAs($this->gerente)->get(route('catalogo.productos.index', ['marca_id' => $marca->id]))
            ->assertViewHas('productos', fn ($productos) => $productos->pluck('id')->all() === [$sandalia->id]);
        $this->actingAs($this->gerente)->get(route('catalogo.productos.index', ['estado' => 'inactivos']))
            ->assertViewHas('productos', fn ($productos) => $productos->pluck('id')->all() === [$inactiva->id]);
        $this->actingAs($this->gerente)->get(route('catalogo.productos.index', ['estado' => 'todos']))
            ->assertViewHas('productos', fn ($productos) => $productos->count() === 3);
        $this->assertNotNull($bota);
    }

    public function test_salesperson_can_view_but_not_manage_the_catalog(): void
    {
        $producto = $this->crearProducto();
        $vendedor = User::factory()->conSucursal(rol: Rol::Vendedor)->create();

        $this->actingAs($vendedor)->get(route('catalogo.productos.index'))->assertOk()->assertDontSee('Nuevo producto');
        $this->actingAs($vendedor)->get(route('catalogo.productos.show', $producto))->assertOk()->assertDontSee('Editar producto');
        $this->actingAs($vendedor)->get(route('catalogo.productos.create'))->assertForbidden();
        $this->actingAs($vendedor)->post(route('catalogo.productos.store'), $this->datos(['nombre' => 'No']))->assertForbidden();
    }

    public function test_product_page_links_to_stock_according_to_permissions(): void
    {
        $producto = $this->crearProducto();
        $variante = $producto->variantes()->first();
        $vendedor = User::factory()->conSucursal(rol: Rol::Vendedor)->create();

        $this->actingAs($this->gerente)->get(route('catalogo.productos.show', $producto))
            ->assertOk()
            ->assertSee(route('stock.ajuste.edit', $producto))
            ->assertSee(route('stock.kardex', $variante));

        $this->actingAs($vendedor)->get(route('catalogo.productos.show', $producto))
            ->assertOk()
            ->assertDontSee(route('stock.ajuste.edit', $producto))
            ->assertSee(route('stock.kardex', $variante));
    }

    public function test_cashier_cannot_change_a_price(): void
    {
        $producto = $this->crearProducto();
        $variante = $producto->variantes()->first();
        $cajero = User::factory()->conSucursal(rol: Rol::Cajero)->create();

        $this->actingAs($cajero)
            ->put(route('catalogo.productos.variantes.update', [$producto, $variante]), $this->datosVariante($variante, ['precio_final' => '1']))
            ->assertForbidden();

        $this->assertSame('89900.00', $variante->fresh()->precio_final);
    }

    public function test_users_without_catalog_permission_cannot_see_it(): void
    {
        $sinPermisos = User::factory()->conSucursal()->create();

        $this->actingAs($sinPermisos)->get(route('catalogo.productos.index'))->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return array_filter(array_merge([
            'nombre' => 'Bota Ana',
            'categoria_id' => $this->botas->id,
            'alicuota_iva_id' => $this->iva21->id,
            'colores' => [$this->negro->id, $this->suela->id],
            'talles' => '35-37',
            'costo' => '40000',
            'precio_final' => '89900',
        ], $cambios), fn ($valor) => $valor !== null);
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function crearProducto(array $cambios = []): Producto
    {
        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.store'), $this->datos($cambios))
            ->assertSessionHasNoErrors();

        Auditoria::query()->toBase()->delete();

        return Producto::latest('id')->first();
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datosVariante(Variante $variante, array $cambios = []): array
    {
        return array_merge([
            'sku' => $variante->sku,
            'codigo_barras' => $variante->codigo_barras,
            'costo' => $variante->costo,
            'precio_final' => $variante->precio_final,
            'activo' => $variante->activo ? '1' : '0',
        ], $cambios);
    }
}
