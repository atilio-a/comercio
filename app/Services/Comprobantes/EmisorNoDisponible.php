<?php

namespace App\Services\Comprobantes;

use App\Exceptions\VentaRechazada;
use App\Models\Comprobante;
use App\Models\Devolucion;
use App\Models\Venta;

/**
 * Emisor que se resuelve cuando la facturación electrónica figura habilitada pero todavía no
 * hay un emisor fiscal: rechaza toda emisión, así nadie cree estar facturando con un
 * comprobante interno. Lo reemplazará el emisor ARCA.
 */
class EmisorNoDisponible implements EmisorComprobante
{
    public const MENSAJE = 'La facturación electrónica todavía no está disponible: deshabilitala en Parámetros para emitir comprobantes internos.';

    public function nombre(): string
    {
        return 'no_disponible';
    }

    public function verificarDisponible(): never
    {
        throw new VentaRechazada(self::MENSAJE, 'items');
    }

    public function emitirVenta(Venta $venta): Comprobante
    {
        $this->verificarDisponible();
    }

    public function emitirAnulacion(Venta $venta, Comprobante $original, string $motivo): Comprobante
    {
        $this->verificarDisponible();
    }

    public function emitirNotaCredito(Devolucion $devolucion): Comprobante
    {
        $this->verificarDisponible();
    }
}
