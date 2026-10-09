<?php

namespace App\Http\Controllers\Stock;

use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Models\MovimientoStock;
use App\Models\Stock;
use App\Models\Variante;
use App\Services\SucursalActiva;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kardex de una variante en la sucursal activa (STK-02).
 */
class KardexController extends Controller
{
    public function show(Request $request, Variante $variante, SucursalActiva $sucursalActiva): View
    {
        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);
        $sucursalId = $sucursalActiva->id();

        $movimientos = MovimientoStock::query()
            ->where('variante_id', $variante->id)
            ->where('sucursal_id', $sucursalId)
            ->with('user:id,name')
            ->when($filtros['desde'] ?? null, fn (Builder $query, $desde) => $query->where('fecha', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn (Builder $query, $hasta) => $query->where('fecha', '<=', $hasta))
            // The balance follows the order of registration, not the business date.
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $stock = Stock::query()->where('variante_id', $variante->id)->where('sucursal_id', $sucursalId)->first();

        return view('stock.kardex', [
            'variante' => $variante->load(['producto:id,nombre', 'color:id,nombre']),
            'movimientos' => $movimientos,
            'filtros' => $filtros,
            'cantidad' => $stock?->cantidad ?? 0,
            'minimo' => $stock?->minimo ?? 0,
            'puedeAjustar' => $request->user()->can(Permiso::StockAjustar->value),
        ]);
    }
}
