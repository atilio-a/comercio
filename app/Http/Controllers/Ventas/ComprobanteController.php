<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Services\SucursalActiva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Impresión de comprobantes (VEN-04) en ticket de 80 mm o A4, desde los datos copiados al
 * emitirlos. El PDF sale de "Imprimir → Guardar como PDF" del navegador. Lo ve quien puede ver
 * la venta; los de otra sucursal responden 404.
 */
class ComprobanteController extends Controller
{
    public const FORMATOS = ['ticket', 'a4'];

    public function show(Request $request, Comprobante $comprobante, SucursalActiva $sucursalActiva): View
    {
        abort_unless((int) $comprobante->sucursal_id === (int) $sucursalActiva->id(), 404);
        abort_unless($comprobante->venta, 404);
        Gate::authorize('view', $comprobante->venta);

        $formato = in_array($request->query('formato'), self::FORMATOS, true) ? $request->query('formato') : 'ticket';

        return view('comprobantes.imprimir', [
            'comprobante' => $comprobante,
            'datos' => $comprobante->datos_json,
            'formato' => $formato,
        ]);
    }
}
