<?php

namespace App\Services;

use App\Enums\Parametro;
use App\Models\Color;
use App\Models\Producto;
use App\Models\ProductoFoto;
use App\Models\Stock;
use App\Models\Variante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Búsqueda de productos para la venta rápida (VEN-01), agrupada por producto para elegir talle
 * y color. Cada color lleva su foto ({@see Producto::fotoPara()}: la primera del color o la
 * principal) y cada variante su precio final y el stock de la sucursal.
 *
 * Con el parámetro "Usar talles en productos" deshabilitado no informa talles: la pantalla
 * ofrece solo el color, y si un color tiene varias variantes se distinguen por su descripción.
 */
class CatalogoVenta
{
    public const LIMITE_PRODUCTOS = 20;

    public function __construct(private Parametros $parametros) {}

    /**
     * Productos con variantes vendibles que coinciden por nombre, SKU o código de barras. Con
     * `$exacto` (escaneo) solo por SKU o código, y cada producto trae solo la variante encontrada.
     *
     * @return list<array<string, mixed>>
     */
    public function buscar(string $texto, bool $exacto, int $sucursalId): array
    {
        $texto = trim($texto);

        if ($texto === '') {
            return [];
        }

        $coincidencias = Variante::query()
            ->vendibles()
            ->where(function (Builder $query) use ($texto, $exacto) {
                $query->where('sku', $texto)->orWhere('codigo_barras', $texto);

                if (! $exacto) {
                    $query->orWhereHas('producto', fn (Builder $producto) => $producto->where('nombre', 'like', '%'.$texto.'%'));
                }
            })
            ->orderBy('producto_id')
            ->limit(200)
            ->get(['id', 'producto_id']);

        $productoIds = $coincidencias->pluck('producto_id')->unique()->take(self::LIMITE_PRODUCTOS)->values();

        $productos = Producto::query()
            ->whereIn('id', $productoIds)
            ->with([
                'alicuotaIva:id,porcentaje',
                'fotos',
                'variantes' => fn ($query) => $query->where('activo', true)
                    ->when($exacto, fn ($query) => $query->whereIn('id', $coincidencias->pluck('id')))
                    ->with('color:id,nombre')
                    ->orderBy('color_id')
                    ->orderBy('talle')
                    ->orderBy('id'),
            ])
            ->orderBy('nombre')
            ->get();

        $stock = Stock::query()
            ->where('sucursal_id', $sucursalId)
            ->whereIn('variante_id', $productos->flatMap->variantes->pluck('id'))
            ->pluck('cantidad', 'variante_id');

        $usarTalles = $this->parametros->habilitado(Parametro::UsarTalles);

        return $productos->map(fn (Producto $producto) => $this->datosProducto($producto, $stock->all(), $usarTalles))->values()->all();
    }

    /**
     * @param  array<int, int>  $stock  Cantidad por id de variante.
     * @return array<string, mixed>
     */
    private function datosProducto(Producto $producto, array $stock, bool $usarTalles): array
    {
        /** @var Collection<int, Variante> $variantes */
        $variantes = $producto->variantes;

        return [
            'producto_id' => $producto->id,
            'nombre' => $producto->nombre,
            'alicuota' => (string) $producto->alicuotaIva->porcentaje,
            'usa_talles' => $usarTalles && $variantes->contains(fn (Variante $variante) => $variante->talle !== null),
            'colores' => $variantes->pluck('color')->unique('id')->map(fn (Color $color) => [
                'color_id' => $color->id,
                'nombre' => $color->nombre,
                'foto' => $this->urlFoto($producto->fotoPara($color)),
            ])->values()->all(),
            'variantes' => $variantes->map(function (Variante $variante) use ($producto, $stock, $usarTalles) {
                $datos = [
                    'variante_id' => $variante->id,
                    'producto' => $producto->nombre,
                    'color_id' => $variante->color_id,
                    'color' => $variante->color->nombre,
                    'descripcion' => $variante->descripcion(),
                    'sku' => $variante->sku,
                    'codigo' => $variante->codigo_barras,
                    'precio_final' => (string) $variante->precio_final,
                    'stock' => (int) ($stock[$variante->id] ?? 0),
                ];

                if ($usarTalles) {
                    $datos['talle'] = $variante->talle;
                }

                return $datos;
            })->values()->all(),
        ];
    }

    private function urlFoto(?ProductoFoto $foto): ?string
    {
        return $foto ? route('catalogo.fotos.mostrar', [$foto, 'miniatura']) : null;
    }
}
