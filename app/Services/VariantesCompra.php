<?php

namespace App\Services;

use App\Models\Variante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Búsqueda de variantes para cargar órdenes y recepciones, y los datos que usa
 * el componente de ítems de compra en el navegador.
 */
class VariantesCompra
{
    public const LIMITE = 20;

    /**
     * Variantes vendibles por nombre de producto, SKU o código exactos. Con `$exacto`
     * solo por SKU o código de barras (escaneo).
     *
     * @return Collection<int, Variante>
     */
    public function buscar(string $texto, bool $exacto = false): Collection
    {
        $texto = trim($texto);

        if ($texto === '') {
            return collect();
        }

        return Variante::query()
            ->vendibles()
            ->with(['producto:id,nombre', 'color:id,nombre'])
            ->where(function (Builder $query) use ($texto, $exacto) {
                $query->where('sku', $texto)->orWhere('codigo_barras', $texto);

                if (! $exacto) {
                    $query->orWhereHas('producto', fn (Builder $producto) => $producto->where('nombre', 'like', '%'.$texto.'%'));
                }
            })
            ->orderBy('producto_id')
            ->orderBy('id')
            ->limit(self::LIMITE)
            ->get();
    }

    /**
     * @return array{variante_id: int, producto: string, descripcion: string, sku: string, codigo: string, costo: string}
     */
    public function datos(Variante $variante): array
    {
        return [
            'variante_id' => $variante->id,
            'producto' => $variante->producto->nombre,
            'descripcion' => $variante->descripcion(),
            'sku' => $variante->sku,
            'codigo' => $variante->codigo_barras,
            'costo' => (string) $variante->costo,
        ];
    }

    /**
     * Ítems enviados en el formulario (p. ej. de `old()`), completados con los datos de
     * cada variante para volver a mostrarlos. Descarta variantes inexistentes.
     *
     * @param  array<int|string, mixed>  $entrada
     * @return list<array<string, mixed>>
     */
    public function hidratar(array $entrada): array
    {
        $ids = collect($entrada)->pluck('variante_id')->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id);
        $variantes = Variante::query()->with(['producto:id,nombre', 'color:id,nombre'])->whereIn('id', $ids)->get()->keyBy('id');
        $items = [];

        foreach ($entrada as $item) {
            $variante = is_array($item) && is_numeric($item['variante_id'] ?? null) ? $variantes->get((int) $item['variante_id']) : null;

            if (! $variante) {
                continue;
            }

            $items[] = [
                ...$this->datos($variante),
                'cantidad' => (string) ($item['cantidad'] ?? ''),
                'costo' => (string) ($item['costo'] ?? $variante->costo),
                'actualizar_costo' => filter_var($item['actualizar_costo'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $items;
    }
}
