<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Variante;
use App\Services\SucursalActiva;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    /**
     * Stock actual de la sucursal activa por variante. Las variantes sin fila de stock
     * figuran con cantidad y mínimo 0.
     */
    public function index(Request $request, SucursalActiva $sucursalActiva): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'categoria_id' => ['nullable', 'integer'],
            'bajo_minimo' => ['nullable', 'boolean'],
            'estado' => ['nullable', 'in:vendibles,todas'],
        ]);
        $estado = $filtros['estado'] ?? 'vendibles';
        $sucursalId = $sucursalActiva->id();

        $variantes = Variante::query()
            ->join('productos', 'productos.id', '=', 'variantes.producto_id')
            ->join('colores', 'colores.id', '=', 'variantes.color_id')
            ->leftJoin('stock', function (JoinClause $join) use ($sucursalId) {
                $join->on('stock.variante_id', '=', 'variantes.id')->where('stock.sucursal_id', $sucursalId);
            })
            ->select('variantes.*')
            ->selectRaw('productos.nombre as producto_nombre, COALESCE(stock.cantidad, 0) as stock_cantidad, COALESCE(stock.minimo, 0) as stock_minimo')
            ->with(['color:id,nombre', 'producto:id', 'producto.fotoPrincipal'])
            ->when($filtros['q'] ?? null, function (Builder $query, string $texto) {
                $query->where(function (Builder $query) use ($texto) {
                    $query->where('productos.nombre', 'like', '%'.$texto.'%')
                        ->orWhere('variantes.sku', $texto)
                        ->orWhere('variantes.codigo_barras', $texto);
                });
            })
            ->when($filtros['categoria_id'] ?? null, fn (Builder $query, $id) => $query->where('productos.categoria_id', $id))
            ->when($request->boolean('bajo_minimo'), fn (Builder $query) => $query
                ->where('stock.minimo', '>', 0)
                ->whereColumn('stock.cantidad', '<=', 'stock.minimo'))
            ->when($estado === 'vendibles', fn (Builder $query) => $query
                ->where('variantes.activo', true)
                ->where('productos.activo', true))
            ->orderBy('productos.nombre')
            ->orderBy('colores.nombre')
            ->orderByRaw('LENGTH(variantes.talle), variantes.talle')
            ->paginate(50)
            ->withQueryString();

        return view('stock.index', [
            'variantes' => $variantes,
            'filtros' => [...$filtros, 'estado' => $estado, 'bajo_minimo' => $request->boolean('bajo_minimo')],
            'categorias' => Categoria::query()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }
}
