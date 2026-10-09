<?php

namespace App\Services;

use App\Models\Color;
use App\Models\Producto;
use App\Models\ProductoFoto;
use DomainException;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Administra las imágenes de un producto: archivos en el disco privado `local`,
 * orden de carga, imagen principal única y auditoría.
 */
class GaleriaProducto
{
    public const DISCO = 'local';

    public const MAXIMO_POR_PRODUCTO = 10;

    public function __construct(
        private RegistradorAuditoria $auditoria,
    ) {}

    /**
     * Agrega imágenes al final de la galería. Si la operación falla, borra los archivos
     * que alcanzó a escribir.
     *
     * @param  list<UploadedFile>  $fotos
     * @param  array<int, UploadedFile|null>  $miniaturas  Por índice de $fotos; si falta, se usa la imagen.
     * @return Collection<int, ProductoFoto>
     *
     * @throws DomainException si se supera el máximo por producto.
     */
    public function agregar(Producto $producto, array $fotos, array $miniaturas = [], ?Color $color = null): Collection
    {
        $escritos = [];

        try {
            return DB::transaction(function () use ($producto, $fotos, $miniaturas, $color, &$escritos) {
                $this->bloquear($producto);

                if ($producto->fotos()->count() + count($fotos) > self::MAXIMO_POR_PRODUCTO) {
                    throw new DomainException('Un producto puede tener hasta '.self::MAXIMO_POR_PRODUCTO.' imágenes.');
                }

                $orden = (int) $producto->fotos()->max('orden');
                $creadas = new Collection;

                foreach (array_values($fotos) as $indice => $archivo) {
                    $nombre = (string) Str::uuid();
                    $carpeta = "productos/{$producto->id}";

                    $ruta = $escritos[] = $archivo->storeAs($carpeta, $nombre.'.'.$this->extension($archivo), self::DISCO);
                    $miniatura = $miniaturas[$indice] ?? null;
                    $rutaMiniatura = $miniatura
                        ? ($escritos[] = $miniatura->storeAs($carpeta, $nombre.'-min.'.$this->extension($miniatura), self::DISCO))
                        : $ruta;

                    [$ancho, $alto] = getimagesize($archivo->getRealPath()) ?: [0, 0];

                    $foto = $producto->fotos()->create([
                        'color_id' => $color?->id,
                        'ruta' => $ruta,
                        'ruta_miniatura' => $rutaMiniatura,
                        'es_principal' => false,
                        'orden' => ++$orden,
                        'ancho' => $ancho,
                        'alto' => $alto,
                        'bytes' => $archivo->getSize(),
                    ]);

                    $this->auditoria->registrar('producto.foto_agregada', $producto, null, [
                        'foto_id' => $foto->id,
                        'color' => $color?->nombre,
                        'ancho' => $ancho,
                        'alto' => $alto,
                    ]);

                    $creadas->push($foto);
                }

                $this->asegurarPrincipal($producto);

                return $creadas->map->fresh();
            });
        } catch (Throwable $error) {
            Storage::disk(self::DISCO)->delete($escritos);

            throw $error;
        }
    }

    public function marcarPrincipal(ProductoFoto $foto): void
    {
        DB::transaction(function () use ($foto) {
            $this->bloquear($foto->producto);

            $anterior = $foto->producto->fotos()->where('es_principal', true)->value('id');

            if ($anterior === $foto->id) {
                return;
            }

            ProductoFoto::where('producto_id', $foto->producto_id)->update(['es_principal' => false]);
            $foto->forceFill(['es_principal' => true])->save();

            $this->auditoria->registrar('producto.foto_principal', $foto->producto, ['foto_principal_id' => $anterior], ['foto_principal_id' => $foto->id]);
        });
    }

    public function cambiarColor(ProductoFoto $foto, ?Color $color): void
    {
        if ($foto->color_id === $color?->id) {
            return;
        }

        DB::transaction(function () use ($foto, $color) {
            $anterior = $foto->color?->nombre;
            $foto->color()->associate($color)->save();

            $this->auditoria->registrar('producto.foto_color', $foto->producto, ['foto_id' => $foto->id, 'color' => $anterior], ['foto_id' => $foto->id, 'color' => $color?->nombre]);
        });
    }

    /**
     * Borra la imagen; los archivos se eliminan recién cuando se confirma la transacción.
     */
    public function eliminar(ProductoFoto $foto): void
    {
        DB::transaction(function () use ($foto) {
            $producto = $foto->producto;
            $this->bloquear($producto);

            $archivos = array_values(array_unique([$foto->ruta, $foto->ruta_miniatura]));
            $foto->delete();

            $this->asegurarPrincipal($producto);

            $this->auditoria->registrar('producto.foto_eliminada', $producto, [
                'foto_id' => $foto->id,
                'color' => $foto->color?->nombre,
                'era_principal' => $foto->es_principal,
            ], null);

            DB::afterCommit(fn () => Storage::disk(self::DISCO)->delete($archivos));
        });
    }

    /**
     * Si no queda ninguna principal, la primera en orden de carga pasa a serlo.
     */
    private function asegurarPrincipal(Producto $producto): void
    {
        if ($producto->fotos()->where('es_principal', true)->exists()) {
            return;
        }

        $producto->fotos()->first()?->forceFill(['es_principal' => true])->save();
    }

    /**
     * Serializa las operaciones sobre la galería de un mismo producto.
     */
    private function bloquear(Producto $producto): void
    {
        Producto::query()->whereKey($producto->id)->lockForUpdate()->first();
    }

    /**
     * Tipo según el contenido real del archivo (fileinfo), no según el nombre.
     */
    public static function tipoReal(UploadedFile $archivo): ?string
    {
        $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($archivo->getRealPath());

        return $tipo === false ? null : $tipo;
    }

    private function extension(UploadedFile $archivo): string
    {
        return match (self::tipoReal($archivo)) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
