<?php

namespace App\Services\Comprobantes;

use App\Exceptions\VentaRechazada;
use App\Models\Comprobante;
use App\Models\Devolucion;
use App\Models\Venta;

/**
 * Emisión de comprobantes desacoplada de la venta (doc 01, decisión de diseño 2; RNF-07).
 *
 * Hoy la implementación es {@see EmisorInterno}; con la facturación electrónica habilitada se
 * usará un emisor ARCA (WSFE o proveedor intermediario) sin cambiar las ventas. El binding
 * está en `AppServiceProvider::register()`.
 *
 * Contrato: se llama dentro de la transacción de la operación (la numeración bloquea el punto
 * de venta hasta el final), y el comprobante devuelto ya está grabado y es inmutable.
 */
interface EmisorComprobante
{
    /**
     * Identificador del emisor que se guarda en `comprobantes.emisor`.
     */
    public function nombre(): string;

    /**
     * Verifica que el emisor pueda emitir, antes de grabar nada.
     *
     * @throws VentaRechazada
     */
    public function verificarDisponible(): void;

    /**
     * Emite el comprobante de una venta recién registrada.
     *
     * @throws VentaRechazada
     */
    public function emitirVenta(Venta $venta): Comprobante;

    /**
     * Emite el comprobante que anula al original (con ARCA, una nota de crédito).
     *
     * @throws VentaRechazada
     */
    public function emitirAnulacion(Venta $venta, Comprobante $original, string $motivo): Comprobante;

    /**
     * Emite la nota de crédito de una devolución recién registrada (con ARCA, la nota de crédito fiscal).
     *
     * @throws VentaRechazada
     */
    public function emitirNotaCredito(Devolucion $devolucion): Comprobante;
}
