<?php

namespace Tests\Feature\Catalogo;

use App\Models\Auditoria;
use App\Models\Color;
use App\Models\Producto;
use App\Models\ProductoFoto;
use App\Models\User;
use App\Services\GaleriaProducto;
use App\Services\RegistradorAuditoria;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\DiscoDeFotosFalso;
use Tests\Support\ImagenesDePrueba;
use Tests\TestCase;

class GaleriaProductoTest extends TestCase
{
    use DiscoDeFotosFalso, RefreshDatabase;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fingirDiscoDeFotos();
        $this->actingAs(User::factory()->create());
        $this->producto = Producto::factory()->create(['nombre' => 'Bota Ana']);
    }

    public function test_photos_are_added_in_order_and_the_first_one_is_main(): void
    {
        $fotos = $this->agregar(3);

        $this->assertSame([1, 2, 3], $fotos->pluck('orden')->all());
        $this->assertSame([true, false, false], $fotos->pluck('es_principal')->all());

        foreach ($fotos as $foto) {
            Storage::disk(GaleriaProducto::DISCO)->assertExists([$foto->ruta, $foto->ruta_miniatura]);
            $this->assertStringStartsWith("productos/{$this->producto->id}/", $foto->ruta);
            $this->assertSame([1600, 1200], [$foto->ancho, $foto->alto]);
            $this->assertGreaterThan(0, $foto->bytes);
        }

        $this->assertSame(3, Auditoria::where('accion', 'producto.foto_agregada')->count());
    }

    public function test_file_names_and_extensions_come_from_the_server(): void
    {
        [$foto] = $this->galeria()->agregar($this->producto, [ImagenesDePrueba::archivoPng('../../malicioso.jpg')]);

        $this->assertMatchesRegularExpression('#^productos/\d+/[0-9a-f-]{36}\.png$#', $foto->ruta);
    }

    public function test_thumbnail_is_stored_when_sent_and_the_image_is_used_otherwise(): void
    {
        [$conMiniatura, $sinMiniatura] = $this->galeria()->agregar(
            $this->producto,
            [ImagenesDePrueba::archivoJpeg(), ImagenesDePrueba::archivoJpeg()],
            [0 => ImagenesDePrueba::archivoJpeg('min.jpg', 400, 300)],
        );

        $this->assertStringEndsWith('-min.jpg', $conMiniatura->ruta_miniatura);
        $this->assertSame($sinMiniatura->ruta, $sinMiniatura->ruta_miniatura);
    }

    public function test_more_than_ten_photos_are_rejected_without_leaving_files(): void
    {
        $this->agregar(9);

        try {
            $this->agregar(2);
            $this->fail('Debía rechazarse por superar el máximo.');
        } catch (DomainException $error) {
            $this->assertStringContainsString('hasta 10', $error->getMessage());
        }

        $this->assertSame(9, $this->producto->fotos()->count());
        $this->assertCount(9, Storage::disk(GaleriaProducto::DISCO)->allFiles());
    }

    public function test_a_failure_rolls_back_and_removes_written_files(): void
    {
        $this->mock(RegistradorAuditoria::class)
            ->shouldReceive('registrar')
            ->andThrow(new RuntimeException('falla simulada'));

        try {
            $this->galeria()->agregar($this->producto, [ImagenesDePrueba::archivoPng()]);
            $this->fail('Se esperaba la falla simulada.');
        } catch (RuntimeException) {
        }

        $this->assertSame(0, ProductoFoto::count());
        $this->assertSame([], Storage::disk(GaleriaProducto::DISCO)->allFiles());
    }

    public function test_main_photo_can_be_changed_and_stays_unique(): void
    {
        [, $segunda] = $this->agregar(3);

        $this->galeria()->marcarPrincipal($segunda);

        $this->assertSame([$segunda->id], $this->producto->fotos()->where('es_principal', true)->pluck('id')->all());
        $this->assertSame(['foto_principal_id' => $segunda->id], Auditoria::where('accion', 'producto.foto_principal')->sole()->despues);
    }

    public function test_deleting_the_main_photo_promotes_the_next_and_removes_files(): void
    {
        [$primera, $segunda] = $this->agregar(3);

        $this->galeria()->eliminar($primera);

        $this->assertModelMissing($primera);
        $this->assertTrue($segunda->fresh()->es_principal);
        Storage::disk(GaleriaProducto::DISCO)->assertMissing([$primera->ruta, $primera->ruta_miniatura]);
        Storage::disk(GaleriaProducto::DISCO)->assertExists($segunda->ruta);
        $this->assertTrue(Auditoria::where('accion', 'producto.foto_eliminada')->sole()->antes['era_principal']);
    }

    public function test_color_can_be_assigned_and_removed(): void
    {
        $suela = Color::factory()->create(['nombre' => 'Suela']);
        [$foto] = $this->agregar(1);

        $this->galeria()->cambiarColor($foto, $suela);
        $this->assertSame($suela->id, $foto->fresh()->color_id);

        $this->galeria()->cambiarColor($foto->fresh(), null);
        $this->assertNull($foto->fresh()->color_id);

        $registros = Auditoria::where('accion', 'producto.foto_color')->orderBy('id')->get();
        $this->assertSame(['Suela', null], $registros->pluck('despues.color')->all());
    }

    public function test_photos_can_be_added_for_a_color(): void
    {
        $suela = Color::factory()->create();

        [$foto] = $this->galeria()->agregar($this->producto, [ImagenesDePrueba::archivoPng()], [], $suela);

        $this->assertSame($suela->id, $foto->color_id);
    }

    /**
     * @return Collection<int, ProductoFoto>
     */
    private function agregar(int $cantidad)
    {
        $fotos = [];

        for ($i = 0; $i < $cantidad; $i++) {
            $fotos[] = ImagenesDePrueba::archivoPng("foto{$i}.png");
        }

        return $this->galeria()->agregar($this->producto, $fotos);
    }

    private function galeria(): GaleriaProducto
    {
        return app(GaleriaProducto::class);
    }
}
