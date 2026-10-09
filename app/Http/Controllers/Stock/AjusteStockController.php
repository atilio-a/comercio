<?php

namespace App\Http\Controllers\Stock;

use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\AjusteStockRequest;
use App\Models\Producto;
use App\Models\Stock;
use App\Services\MovimientosStock;
use App\Services\SucursalActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Ajuste por conteo de todas las variantes de un producto en la sucursal activa.
 */
class AjusteStockController extends Controller
{
    public function edit(Producto $producto, SucursalActiva $sucursalActiva): View
    {
        Gate::authorize(Permiso::StockAjustar->value);

        $variantes = $producto->variantes()->with('color:id,nombre')->orderBy('id')->get();
        $stock = Stock::query()
            ->where('sucursal_id', $sucursalActiva->id())
            ->whereIn('variante_id', $variantes->modelKeys())
            ->get()
            ->keyBy('variante_id');

        return view('stock.ajuste', [
            'producto' => $producto,
            'variantes' => $variantes,
            'stock' => $stock,
        ]);
    }

    public function update(AjusteStockRequest $request, Producto $producto, MovimientosStock $movimientos, SucursalActiva $sucursalActiva): RedirectResponse
    {
        $sucursal = $sucursalActiva->sucursal();
        $conteos = $request->conteos();
        $minimos = $request->minimos();
        $fecha = Carbon::parse($request->validated('fecha'));
        $motivo = trim((string) $request->validated('motivo'));

        $ajustes = DB::transaction(function () use ($producto, $sucursal, $conteos, $minimos, $fecha, $motivo, $movimientos) {
            $ajustes = 0;

            // Ascending variant id: the lock order every stock operation must follow.
            foreach ($producto->variantes()->orderBy('id')->get() as $variante) {
                if (array_key_exists($variante->id, $conteos)
                    && $movimientos->ajustarAConteo($variante, $sucursal, $conteos[$variante->id], $motivo, $fecha)) {
                    $ajustes++;
                }

                if (array_key_exists($variante->id, $minimos)) {
                    $movimientos->actualizarMinimo($variante, $sucursal, $minimos[$variante->id]);
                }
            }

            return $ajustes;
        });

        return redirect()->route('stock.ajuste.edit', $producto)
            ->with('status', $ajustes === 0 ? 'No hubo diferencias de stock.' : "Se registraron {$ajustes} ajustes.");
    }
}
