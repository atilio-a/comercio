<?php

namespace Tests\Support;

use App\Services\GaleriaProducto;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Disco falso para las fotos, en una carpeta distinta por test.
 *
 * Storage::fake() reutiliza siempre la misma carpeta y la vacía al empezar; en Windows,
 * si un test anterior dejó un archivo abierto (por ejemplo, un stream de respuesta), la
 * carpeta queda con el borrado pendiente y no se puede volver a crear.
 */
trait DiscoDeFotosFalso
{
    private string $raizDiscoDeFotos;

    protected function fingirDiscoDeFotos(): void
    {
        // Best effort: folders whose deletion failed in earlier tests (open handles on Windows).
        foreach (File::glob(storage_path('framework/testing/disks/fotos-*')) as $anterior) {
            File::deleteDirectory($anterior);
        }

        $this->raizDiscoDeFotos = storage_path('framework/testing/disks/fotos-'.Str::random(12));

        // Storage::fake() always uses the same folder, so the disk is built by hand.
        Storage::set(GaleriaProducto::DISCO, Storage::build([
            'driver' => 'local',
            'root' => $this->raizDiscoDeFotos,
            'throw' => false,
        ]));

        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->raizDiscoDeFotos));
    }
}
