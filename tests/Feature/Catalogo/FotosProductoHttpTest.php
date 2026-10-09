<?php

namespace Tests\Feature\Catalogo;

use App\Enums\Rol;
use App\Models\Color;
use App\Models\Producto;
use App\Models\ProductoFoto;
use App\Models\User;
use App\Models\Variante;
use App\Services\GaleriaProducto;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DiscoDeFotosFalso;
use Tests\Support\ImagenesDePrueba;
use Tests\TestCase;

class FotosProductoHttpTest extends TestCase
{
    use DiscoDeFotosFalso, RefreshDatabase;

    private User $gerente;

    private Producto $producto;

    private Color $negro;

    private Color $suela;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fingirDiscoDeFotos();
        $this->seed(RolesYPermisosSeeder::class);
        $this->gerente = User::factory()->conSucursal(rol: Rol::Gerente)->create();
        $this->producto = Producto::factory()->create(['nombre' => 'Bota Ana']);
        $this->negro = Color::factory()->create(['nombre' => 'Negro']);
        $this->suela = Color::factory()->create(['nombre' => 'Suela']);
        Variante::factory()->for($this->producto)->for($this->negro)->create(['talle' => '37']);
        Variante::factory()->for($this->producto)->for($this->suela)->create(['talle' => '37']);
    }

    public function test_manager_uploads_several_photos_with_thumbnails(): void
    {
        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.fotos.store', $this->producto), [
                'fotos' => [ImagenesDePrueba::archivoJpeg('a.jpg'), ImagenesDePrueba::archivoPng('b.png')],
                'miniaturas' => [ImagenesDePrueba::archivoJpeg('a-min.jpg', 400, 300), ImagenesDePrueba::archivoPng('b-min.png', 400, 300)],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Se agregaron 2 imágenes.');

        $this->assertSame(2, $this->producto->fotos()->count());
        $this->assertTrue($this->producto->fotos()->first()->es_principal);
    }

    public function test_photos_can_be_uploaded_for_a_color_of_the_product(): void
    {
        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.fotos.store', $this->producto), [
                'fotos' => [ImagenesDePrueba::archivoPng()],
                'color_id' => $this->suela->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->suela->id, $this->producto->fotos()->sole()->color_id);
    }

    public function test_a_color_without_variants_in_the_product_is_rejected(): void
    {
        $ajeno = Color::factory()->create();

        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.fotos.store', $this->producto), [
                'fotos' => [ImagenesDePrueba::archivoPng()],
                'color_id' => $ajeno->id,
            ])
            ->assertSessionHasErrors(['color_id' => 'El color elegido no corresponde a ninguna variante del producto.']);

        $foto = $this->subirUna();
        $this->actingAs($this->gerente)
            ->put(route('catalogo.productos.fotos.color', [$this->producto, $foto]), ['color_id' => $ajeno->id])
            ->assertSessionHasErrors('color_id');
    }

    public function test_a_renamed_pdf_is_rejected_by_content(): void
    {
        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.fotos.store', $this->producto), ['fotos' => [ImagenesDePrueba::pdfDisfrazado()]])
            ->assertSessionHasErrors(['fotos.0' => 'Solo se admiten imágenes JPEG, PNG o WebP.']);

        $this->assertSame(0, ProductoFoto::count());
    }

    public function test_files_larger_than_5mb_are_rejected(): void
    {
        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.fotos.store', $this->producto), [
                'fotos' => [UploadedFile::fake()->create('grande.jpg', 6000, 'image/jpeg')],
            ])
            ->assertSessionHasErrors(['fotos.0' => 'Cada imagen puede pesar hasta 5 MB.']);
    }

    public function test_images_larger_than_4000px_are_rejected(): void
    {
        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.fotos.store', $this->producto), ['fotos' => [ImagenesDePrueba::archivoJpeg('enorme.jpg', 4500, 3000)]])
            ->assertSessionHasErrors(['fotos.0' => 'Cada imagen puede medir hasta 4000 px por lado.']);
    }

    public function test_exceeding_the_quota_of_ten_is_rejected(): void
    {
        ProductoFoto::factory()->count(9)->for($this->producto)->create();

        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.fotos.store', $this->producto), [
                'fotos' => [ImagenesDePrueba::archivoPng('a.png'), ImagenesDePrueba::archivoPng('b.png')],
            ])
            ->assertSessionHasErrors(['fotos' => 'Un producto puede tener hasta 10 imágenes; quedan 1 lugares.']);

        $this->assertSame(9, $this->producto->fotos()->count());
    }

    public function test_salesperson_sees_photos_but_cannot_manage_them(): void
    {
        $foto = $this->subirUna();
        $vendedor = User::factory()->conSucursal(rol: Rol::Vendedor)->create();

        $this->actingAs($vendedor)->get(route('catalogo.fotos.mostrar', [$foto, 'original']))->assertOk();
        $this->actingAs($vendedor)
            ->post(route('catalogo.productos.fotos.store', $this->producto), ['fotos' => [ImagenesDePrueba::archivoPng()]])
            ->assertForbidden();
        $this->actingAs($vendedor)->put(route('catalogo.productos.fotos.principal', [$this->producto, $foto]))->assertForbidden();
        $this->actingAs($vendedor)->delete(route('catalogo.productos.fotos.destroy', [$this->producto, $foto]))->assertForbidden();

        $this->assertModelExists($foto);
    }

    public function test_guests_are_redirected_to_login_and_get_no_file(): void
    {
        $foto = $this->subirUna();
        $this->post('/logout');

        $this->get(route('catalogo.fotos.mostrar', [$foto, 'original']))->assertRedirect(route('login'));
    }

    public function test_users_without_catalog_permission_cannot_see_photos(): void
    {
        $foto = $this->subirUna();
        $sinRol = User::factory()->conSucursal()->create();

        $this->actingAs($sinRol)->get(route('catalogo.fotos.mostrar', [$foto, 'original']))->assertForbidden();
    }

    public function test_file_is_served_with_its_type_and_private_cache(): void
    {
        $foto = $this->subirUna();

        $respuesta = $this->actingAs($this->gerente)->get(route('catalogo.fotos.mostrar', [$foto, 'original']));

        $respuesta->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('private', $respuesta->headers->get('Cache-Control'));
        $this->assertSame(Storage::disk(GaleriaProducto::DISCO)->get($foto->ruta), $respuesta->streamedContent());

        $this->actingAs($this->gerente)
            ->withHeaders(['If-None-Match' => $respuesta->headers->get('ETag')])
            ->get(route('catalogo.fotos.mostrar', [$foto, 'original']))
            ->assertStatus(304);
    }

    public function test_thumbnail_route_serves_the_thumbnail(): void
    {
        $this->actingAs($this->gerente)->post(route('catalogo.productos.fotos.store', $this->producto), [
            'fotos' => [ImagenesDePrueba::archivoJpeg()],
            'miniaturas' => [ImagenesDePrueba::archivoJpeg('min.jpg', 400, 300)],
        ]);
        $foto = $this->producto->fotos()->sole();

        $respuesta = $this->actingAs($this->gerente)->get(route('catalogo.fotos.mostrar', [$foto, 'miniatura']));

        $this->assertSame(Storage::disk(GaleriaProducto::DISCO)->get($foto->ruta_miniatura), $respuesta->streamedContent());
        $this->actingAs($this->gerente)->get('/catalogo/fotos/'.$foto->id.'/otra')->assertNotFound();
    }

    public function test_manager_changes_main_color_and_deletes(): void
    {
        $primera = $this->subirUna();
        $segunda = $this->subirUna();

        $this->actingAs($this->gerente)->put(route('catalogo.productos.fotos.principal', [$this->producto, $segunda]))->assertSessionHasNoErrors();
        $this->assertTrue($segunda->fresh()->es_principal);

        $this->actingAs($this->gerente)->put(route('catalogo.productos.fotos.color', [$this->producto, $primera]), ['color_id' => $this->negro->id])->assertSessionHasNoErrors();
        $this->assertSame($this->negro->id, $primera->fresh()->color_id);

        $this->actingAs($this->gerente)->delete(route('catalogo.productos.fotos.destroy', [$this->producto, $segunda]))->assertSessionHasNoErrors();
        $this->assertModelMissing($segunda);
        $this->assertTrue($primera->fresh()->es_principal);
    }

    public function test_photos_of_another_product_are_not_reachable(): void
    {
        $ajena = ProductoFoto::factory()->create();

        $this->actingAs($this->gerente)->delete(route('catalogo.productos.fotos.destroy', [$this->producto, $ajena]))->assertNotFound();
        $this->assertModelExists($ajena);
    }

    private function subirUna(): ProductoFoto
    {
        $this->actingAs($this->gerente)
            ->post(route('catalogo.productos.fotos.store', $this->producto), ['fotos' => [ImagenesDePrueba::archivoPng()]])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('catalogo.productos.show', $this->producto).'#imagenes');

        return $this->producto->fotos()->reorder()->latest('id')->first();
    }
}
