<?php

namespace App\Services\Comprobantes;

use App\Enums\TipoComprobante;
use App\Models\Comprobante;
use App\Models\PuntoVenta;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Numeración correlativa de comprobantes por tipo y punto de venta (VEN-07).
 *
 * Bloquea la fila del punto de venta (`lockForUpdate`) hasta el final de la transacción de
 * quien llama y toma el último número del tipo más uno. Así dos emisiones simultáneas del
 * mismo punto de venta se serializan, y si la transacción se revierte el número no se consume
 * (no quedan huecos). El índice único `(tipo, punto_venta_id, numero)` es la red de seguridad.
 * Debe ser el último bloqueo de la operación (después de stock, caja y cuenta corriente).
 */
class NumeradorComprobantes
{
    /**
     * Próximo número para el tipo en el punto de venta (1 si es el primero).
     */
    public function siguiente(PuntoVenta $puntoVenta, TipoComprobante $tipo): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Los comprobantes se numeran dentro de una transacción.');
        }

        PuntoVenta::query()->whereKey($puntoVenta->id)->lockForUpdate()->sole();

        $ultimo = Comprobante::query()
            ->where('punto_venta_id', $puntoVenta->id)
            ->where('tipo', $tipo->value)
            ->max('numero');

        return (int) $ultimo + 1;
    }
}
