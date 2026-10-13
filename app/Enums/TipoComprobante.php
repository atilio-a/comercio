<?php

namespace App\Enums;

/**
 * Tipos de comprobante (VEN-04, VEN-07). Cada tipo tiene numeración propia por punto de
 * venta. Los fiscales (facturas y notas de crédito de ARCA) se agregarán con su emisor.
 */
enum TipoComprobante: string
{
    case VentaInterna = 'venta_interna';
    case AnulacionInterna = 'anulacion_interna';

    public function etiqueta(): string
    {
        return match ($this) {
            self::VentaInterna => 'Comprobante interno de venta',
            self::AnulacionInterna => 'Comprobante interno de anulación',
        };
    }
}
