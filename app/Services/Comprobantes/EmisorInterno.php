<?php

namespace App\Services\Comprobantes;

use App\Enums\TipoComprobante;
use App\Models\Comprobante;
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
     * @param  array<string, mixed>  $datos
     */
    private function crear(Venta $venta, TipoComprobante $tipo, int $numero, array $datos, ?Comprobante $anulado = null): Comprobante
    {
        return Comprobante::create([
            'sucursal_id' => $venta->sucursal_id,
            'venta_id' => $venta->id,
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
