<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Variante;
use App\Services\VariantesCompra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Buscador de variantes para cargar órdenes y recepciones (JSON). Requiere `compras.ver`
 * (middleware de la ruta).
 */
class BuscarVariantesController extends Controller
{
    public function __invoke(Request $request, VariantesCompra $variantes): JsonResponse
    {
        $filtros = $request->validate([
            'q' => ['required', 'string', 'max:150'],
            'exacto' => ['nullable', 'boolean'],
        ]);

        $encontradas = $variantes->buscar($filtros['q'], $request->boolean('exacto'));

        return response()->json([
            'variantes' => $encontradas->map(fn (Variante $variante) => $variantes->datos($variante))->values(),
        ]);
    }
}
