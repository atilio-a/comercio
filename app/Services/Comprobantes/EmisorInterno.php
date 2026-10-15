<?php

namespace App\Services\Comprobantes;

use App\Enums\ResolucionDevolucion;
use App\Enums\TipoComprobante;
use App\Models\Comprobante;
use App\Models\Devolucion;
use App\Models\DevolucionItem;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\VentaPago;
use App\Services\Importes;

/**
 * Comprobantes internos, no fiscales (VEN-04): rotulados "Documento no válido como factura",
 * sin CAE, numerados por tipo y punto de venta con {@see NumeradorComprobantes}.
 *
 * Guarda en `datos_json` la foto completa del documento (comercio, sucursal, cliente,
 * vendedor, ítems, pagos y totales con el IVA discriminado por alícuota), así la impresión no
 * cambia aunque después cambien precios, clientes o productos.
 */
class EmisorInterno implements EmisorComprobante
{
    public function __construct(private NumeradorComprobantes $numerador) {}

    public function nombre(): string
    {
        return 'interno';
    }

    public function verificarDisponible(): void {}

    public function emitirVenta(Venta $venta): Comprobante
    {
        $venta->loadMissing([
            'sucursal', 'puntoVenta', 'cliente', 'vendedor.user', 'user',
            'items.variante.producto', 'items.variante.color', 'pagos.medioPago',
        ]);

        $tipo = TipoComprobante::VentaInterna;
        $numero = $this->numerador->siguiente($venta->puntoVenta, $tipo);

        return $this->crear($venta, $tipo, $numero, [
            ...$this->encabezado($venta, $tipo, $numero),
            ...$this->cuerpo($venta),
        ]);
    }

    public function emitirAnulacion(Venta $venta, Comprobante $original, string $motivo): Comprobante
    {
        $venta->loadMissing('puntoVenta');

        $tipo = TipoComprobante::AnulacionInterna;
        $numero = $this->numerador->siguiente($original->puntoVenta, $tipo);

        // The cancellation reproduces the original document, so it reads the same snapshot.
        $datos = [
            ...$original->datos_json,
            ...$this->encabezado($venta, $tipo, $numero, $original->puntoVenta->numero),
            'anula' => [
                'numero_completo' => $original->numeroCompleto(),
                'fecha' => $original->fecha->toDateString(),
            ],
            'motivo' => $motivo,
        ];

        return $this->crear($venta, $tipo, $numero, $datos, $original);
    }

    /**
     * Nota de crédito de la devolución, en el punto de venta de la venta, con los ítems
     * devueltos, el reintegro según la resolución y la referencia al comprobante de la venta.
     */
    public function emitirNotaCredito(Devolucion $devolucion): Comprobante
    {
        $devolucion->loadMissing([
            'venta.sucursal', 'venta.puntoVenta', 'venta.vendedor.user', 'venta.comprobante', 'cliente', 'medioPago',
            'items.ventaItem', 'items.variante.producto', 'items.variante.color',
        ]);

        $venta = $devolucion->venta;
        $tipo = TipoComprobante::NotaCreditoInterna;
        $numero = $this->numerador->siguiente($venta->puntoVenta, $tipo);

        $porAlicuota = [];

        foreach ($devolucion->items as $item) {
            $clave = (string) $item->ventaItem->alicuota;
            $porAlicuota[$clave] ??= ['alicuota' => $clave, 'neto' => 0, 'iva' => 0];
            $porAlicuota[$clave]['neto'] += Importes::aCentavos((string) $item->neto);
            $porAlicuota[$clave]['iva'] += Importes::aCentavos((string) $item->iva);
        }

        ksort($porAlicuota, SORT_NUMERIC);

        $reintegro = $devolucion->resolucion === ResolucionDevolucion::Reembolso
            ? $devolucion->medioPago->nombre
            : ResolucionDevolucion::SaldoAFavor->etiqueta();

        $datos = [
            ...$this->encabezado($venta, $tipo, $numero),
            'comercio' => config('app.name'),
            'sucursal' => [
                'nombre' => $venta->sucursal->nombre,
                'domicilio' => $venta->sucursal->domicilio,
            ],
            'cliente' => $devolucion->cliente ? [
                'nombre' => $devolucion->cliente->nombre,
                'documento' => $devolucion->cliente->dni_cuit ? $devolucion->cliente->documentoFormateado() : null,
                'condicion_iva' => $devolucion->cliente->condicion_iva->etiqueta(),
            ] : null,
            'vendedor' => $venta->vendedor->user->name,
            'items' => $devolucion->items->map(function (DevolucionItem $item) {
                $bruto = Importes::aCentavos((string) $item->ventaItem->precio_final) * $item->cantidad;
                $importe = Importes::aCentavos((string) $item->importe);

                return [
                    'descripcion' => $item->variante->producto->nombre.' '.$item->variante->descripcion(),
                    'sku' => $item->variante->sku,
                    'cantidad' => $item->cantidad,
                    'precio_final' => (string) $item->ventaItem->precio_final,
                    'descuento' => Importes::aDecimal(max(0, $bruto - $importe)),
                    'recargo' => Importes::aDecimal(max(0, $importe - $bruto)),
                    'total' => (string) $item->importe,
                    'alicuota' => (string) $item->ventaItem->alicuota,
                    'neto' => (string) $item->neto,
                    'iva' => (string) $item->iva,
                ];
            })->values()->all(),
            'pagos' => [[
                'medio' => $reintegro,
                'cuotas' => null,
                'importe' => (string) $devolucion->importe,
                'recargo' => '0.00',
                'cobrado' => (string) $devolucion->importe,
                'referencia' => null,
            ]],
            'totales' => [
                'descuento' => '0.00',
                'recargo' => '0.00',
                'subtotal_neto' => (string) $devolucion->neto,
                'iva' => (string) $devolucion->iva,
                'total' => (string) $devolucion->importe,
                'iva_por_alicuota' => array_values(array_map(fn (array $fila) => [
                    'alicuota' => $fila['alicuota'],
                    'neto' => Importes::aDecimal($fila['neto']),
                    'iva' => Importes::aDecimal($fila['iva']),
                ], $porAlicuota)),
            ],
            'devolucion' => [
                'id' => $devolucion->id,
                'motivo' => $devolucion->motivo,
                'resolucion' => $devolucion->resolucion->etiqueta(),
            ],
            'referencia' => $venta->comprobante ? [
                'numero_completo' => $venta->comprobante->numeroCompleto(),
                'fecha' => $venta->comprobante->fecha->toDateString(),
            ] : null,
        ];

        return $this->crear($venta, $tipo, $numero, $datos, devolucion: $devolucion);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crear(Venta $venta, TipoComprobante $tipo, int $numero, array $datos, ?Comprobante $anulado = null, ?Devolucion $devolucion = null): Comprobante
    {
        return Comprobante::create([
            'sucursal_id' => $venta->sucursal_id,
            'venta_id' => $venta->id,
            'devolucion_id' => $devolucion?->id,
            'tipo' => $tipo,
            'punto_venta_id' => $anulado?->punto_venta_id ?? $venta->punto_venta_id,
            'numero' => $numero,
            'fecha' => now()->toDateString(),
            'emisor' => $this->nombre(),
            'comprobante_anulado_id' => $anulado?->id,
            'datos_json' => $datos,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function encabezado(Venta $venta, TipoComprobante $tipo, int $numero, ?int $puntoVenta = null): array
    {
        $puntoVenta ??= $venta->puntoVenta->numero;

        return [
            'titulo' => $tipo->etiqueta(),
            'leyenda' => Comprobante::LEYENDA_INTERNO,
            'emisor' => $this->nombre(),
            'punto_venta' => $puntoVenta,
            'numero' => $numero,
            'numero_completo' => Comprobante::formatearNumero($puntoVenta, $numero),
            'fecha' => now()->toDateString(),
            'hora' => now()->format('H:i'),
            'venta_id' => $venta->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cuerpo(Venta $venta): array
    {
        $porAlicuota = [];

        foreach ($venta->items as $item) {
            $clave = (string) $item->alicuota;
            $porAlicuota[$clave] ??= ['alicuota' => $clave, 'neto' => 0, 'iva' => 0];
            $porAlicuota[$clave]['neto'] += Importes::aCentavos((string) $item->neto);
            $porAlicuota[$clave]['iva'] += Importes::aCentavos((string) $item->iva);
        }

        ksort($porAlicuota, SORT_NUMERIC);

        return [
            'comercio' => config('app.name'),
            'sucursal' => [
                'nombre' => $venta->sucursal->nombre,
                'domicilio' => $venta->sucursal->domicilio,
            ],
            'cliente' => $venta->cliente ? [
                'nombre' => $venta->cliente->nombre,
                'documento' => $venta->cliente->dni_cuit ? $venta->cliente->documentoFormateado() : null,
                'condicion_iva' => $venta->cliente->condicion_iva->etiqueta(),
            ] : null,
            'vendedor' => $venta->vendedor->user->name,
            'items' => $venta->items->map(fn (VentaItem $item) => [
                'descripcion' => $item->variante->producto->nombre.' '.$item->variante->descripcion(),
                'sku' => $item->variante->sku,
                'cantidad' => $item->cantidad,
                'precio_final' => (string) $item->precio_final,
                'descuento' => Importes::aDecimal(Importes::aCentavos((string) $item->descuento) + Importes::aCentavos((string) $item->descuento_venta)),
                'recargo' => (string) $item->recargo,
                'total' => (string) $item->total,
                'alicuota' => (string) $item->alicuota,
                'neto' => (string) $item->neto,
                'iva' => (string) $item->iva,
            ])->values()->all(),
            'pagos' => $venta->pagos->map(fn (VentaPago $pago) => [
                'medio' => $pago->medioPago->nombre,
                'cuotas' => $pago->cuotas,
                'importe' => (string) $pago->importe,
                'recargo' => (string) $pago->recargo,
                'cobrado' => $pago->cobrado(),
                'referencia' => $pago->referencia,
            ])->values()->all(),
            'totales' => [
                'descuento' => (string) $venta->descuento,
                'recargo' => (string) $venta->recargo,
                'subtotal_neto' => (string) $venta->subtotal_neto,
                'iva' => (string) $venta->iva,
                'total' => (string) $venta->total,
                'iva_por_alicuota' => array_values(array_map(fn (array $fila) => [
                    'alicuota' => $fila['alicuota'],
                    'neto' => Importes::aDecimal($fila['neto']),
                    'iva' => Importes::aDecimal($fila['iva']),
                ], $porAlicuota)),
            ],
        ];
    }
}
