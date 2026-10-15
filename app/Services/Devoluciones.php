<?php

namespace App\Services;

use App\Enums\Permiso;
use App\Enums\ResolucionDevolucion;
use App\Enums\TipoMovimientoCaja;
use App\Enums\TipoMovimientoStock;
use App\Exceptions\CajaNoAbierta;
use App\Exceptions\DevolucionRechazada;
use App\Exceptions\VentaRechazada;
use App\Http\Middleware\IdentificarDispositivo;
use App\Models\CajaSesion;
use App\Models\Cliente;
use App\Models\Devolucion;
use App\Models\DevolucionItem;
use App\Models\MedioPago;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\VentaPago;
use App\Services\Comprobantes\EmisorComprobante;
use Brick\Math\BigInteger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Devoluciones y cambios de ventas confirmadas (VEN-03; doc 04 "Saldo a favor" y "Devolución de
 * una venta a cuenta"; doc 05 `devoluciones`).
 *
 * `registrar()` graba en una sola transacción la devolución y sus ítems, el reingreso de stock
 * ({@see MovimientosStock}, origen la devolución), el egreso de caja del reembolso
 * ({@see MovimientosCaja}) o el haber en la cuenta corriente del cliente
 * ({@see CuentasCorrientes}), la nota de crédito ({@see EmisorComprobante}) y la auditoría.
 * Bloquea primero la venta (serializa las devoluciones de una misma venta) y después en el
 * orden acordado: stock por `variante_id` ascendente, sesión de caja, cuenta corriente y punto
 * de venta. Si algo falla no queda nada grabado.
 *
 * Importes: cada ítem devuelve la parte proporcional por cantidad de lo cobrado por él
 * (`venta_items.total`, con descuentos y recargo por cuotas), calculada sobre el acumulado:
 * `⌊total × devueltas / vendidas⌋`. Así lo devuelto nunca supera lo cobrado y, al devolver la
 * última unidad, cierra exactamente (también el neto y el IVA).
 *
 * Un cambio es una devolución con saldo a favor seguida de una venta nueva pagada con ese saldo
 * ({@see Ventas::registrar()}): no comparten transacción.
 *
 * Errores: {@see DevolucionRechazada} (con el campo del formulario), {@see CajaNoAbierta} y
 * {@see VentaRechazada} si el emisor de comprobantes no está disponible.
 */
class Devoluciones
{
    public function __construct(
        private CalculadoraIva $iva,
        private MovimientosStock $stock,
        private MovimientosCaja $caja,
        private CuentasCorrientes $cuentas,
        private EmisorComprobante $emisor,
        private RegistradorAuditoria $auditoria,
        private SucursalActiva $sucursalActiva,
        private Request $request,
    ) {}

    /**
     * @param  array<int|string, int>  $cantidades  Unidades a devolver por id de ítem de la venta.
     * @param  Cliente|null  $cliente  Solo se usa si la venta no tiene cliente (consumidor final).
     * @param  MedioPago|null  $medioReembolso  Obligatorio con la resolución reembolso.
     *
     * @throws DevolucionRechazada
     * @throws CajaNoAbierta
     * @throws VentaRechazada
     */
    public function registrar(
        Venta $venta,
        array $cantidades,
        string $motivo,
        ResolucionDevolucion $resolucion,
        ?Cliente $cliente = null,
        ?MedioPago $medioReembolso = null,
        ?string $referencia = null,
    ): Devolucion {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new DevolucionRechazada('Indicá el motivo de la devolución.', 'motivo');
        }

        $cantidades = array_filter($cantidades, fn (int $cantidad) => $cantidad !== 0);

        if ($cantidades === []) {
            throw new DevolucionRechazada('Elegí al menos un producto para devolver.');
        }

        $this->emisor->verificarDisponible();

        return DB::transaction(function () use ($venta, $cantidades, $motivo, $resolucion, $cliente, $medioReembolso, $referencia) {
            $venta = Venta::query()->lockForUpdate()->findOrFail($venta->id);

            if ((int) $venta->sucursal_id !== (int) $this->sucursalActiva->id()) {
                throw new DevolucionRechazada('La venta es de otra sucursal: la devolución se registra en la sucursal de la venta.');
            }

            if ($venta->estaAnulada()) {
                throw new DevolucionRechazada("La venta N° {$venta->id} está anulada: no admite devoluciones.");
            }

            $items = $venta->items()->with(['variante.producto', 'variante.color', 'devolucionItems'])->orderBy('variante_id')->get()->keyBy('id');
            $lineas = $this->lineas($items, $cantidades);

            $cliente = $venta->cliente_id ? $venta->cliente : $cliente;
            $sesion = null;
            $referencia = trim((string) $referencia) === '' ? null : trim((string) $referencia);

            if ($resolucion === ResolucionDevolucion::SaldoAFavor && ! $cliente) {
                throw new DevolucionRechazada('La venta fue a consumidor final: elegí o da de alta el cliente que recibe el saldo a favor.', 'cliente_id');
            }

            if ($resolucion === ResolucionDevolucion::Reembolso) {
                $sesion = $this->verificarReembolso($venta, $medioReembolso, $referencia);
            }

            $importe = array_sum(array_column($lineas, 'importe'));
            $neto = array_sum(array_column($lineas, 'neto'));

            $devolucion = Devolucion::create([
                'sucursal_id' => $venta->sucursal_id,
                'venta_id' => $venta->id,
                'cliente_id' => $cliente?->id,
                'fecha' => now()->toDateString(),
                'motivo' => mb_substr($motivo, 0, 200),
                'resolucion' => $resolucion,
                'importe' => Importes::aDecimal($importe),
                'neto' => Importes::aDecimal($neto),
                'iva' => Importes::aDecimal($importe - $neto),
                'medio_pago_id' => $sesion ? $medioReembolso->id : null,
                'caja_sesion_id' => $sesion?->id,
                'user_id' => Auth::id(),
                'dispositivo' => $this->request->attributes->get(IdentificarDispositivo::ATRIBUTO),
            ]);

            foreach ($lineas as $linea) {
                DevolucionItem::create([
                    'devolucion_id' => $devolucion->id,
                    'venta_item_id' => $linea['item']->id,
                    'variante_id' => $linea['item']->variante_id,
                    'cantidad' => $linea['cantidad'],
                    'importe' => Importes::aDecimal($linea['importe']),
                    'neto' => Importes::aDecimal($linea['neto']),
                    'iva' => Importes::aDecimal($linea['iva']),
                ]);
            }

            $concepto = "Devolución N° {$devolucion->id} de la venta N° {$venta->id}";

            // Items are sorted by variant id: the lock order every stock operation must follow.
            foreach ($lineas as $linea) {
                $this->stock->registrar($linea['item']->variante, $venta->sucursal, TipoMovimientoStock::Ingreso, $linea['cantidad'], $concepto, $devolucion);
            }

            if ($sesion) {
                $this->caja->registrar(TipoMovimientoCaja::Devolucion, $medioReembolso, Importes::aDecimal(-$importe), $concepto, $devolucion, $sesion, $referencia);
            } else {
                $this->cuentas->acreditar($cliente, Importes::aDecimal($importe), $concepto, $devolucion, sucursal: $venta->sucursal);
            }

            $notaCredito = $this->emisor->emitirNotaCredito($devolucion);

            $this->auditoria->registrar(
                $resolucion === ResolucionDevolucion::Reembolso ? 'devoluciones.reembolso' : 'devoluciones.saldo_a_favor',
                $devolucion,
                null,
                [
                    'venta_id' => $venta->id,
                    'cliente_id' => $cliente?->id,
                    'motivo' => $devolucion->motivo,
                    'resolucion' => $resolucion->value,
                    'importe' => (string) $devolucion->importe,
                    'medio_pago_id' => $devolucion->medio_pago_id,
                    'nota_credito' => $notaCredito->numeroCompleto(),
                    'items' => array_map(fn (array $linea) => [
                        'variante_id' => $linea['item']->variante_id,
                        'cantidad' => $linea['cantidad'],
                        'importe' => Importes::aDecimal($linea['importe']),
                    ], $lineas),
                ],
            );

            return $devolucion;
        });
    }

    /**
     * Unidades ya devueltas de cada ítem de la venta, por id de ítem.
     *
     * @return array<int, int>
     */
    public function cantidadesDevueltas(Venta $venta): array
    {
        return DevolucionItem::query()
            ->whereIn('venta_item_id', $venta->items()->select('id'))
            ->groupBy('venta_item_id')
            ->selectRaw('venta_item_id, SUM(cantidad) as devueltas')
            ->pluck('devueltas', 'venta_item_id')
            ->map(fn ($devueltas) => (int) $devueltas)
            ->all();
    }

    /**
     * Importes de devolver `$cantidad` unidades de un ítem del que ya se devolvieron
     * `$devueltas` por `$importeDevuelto`, `$netoDevuelto` e `$ivaDevuelto` (en centavos).
     *
     * @return array{importe: int, neto: int, iva: int}
     */
    public function importes(VentaItem $item, int $cantidad, int $devueltas = 0, int $importeDevuelto = 0, int $netoDevuelto = 0, int $ivaDevuelto = 0): array
    {
        $total = Importes::aCentavos((string) $item->total);
        $acumulado = $devueltas + $cantidad;

        // Arbitrary precision: total × units can exceed 64 bits on large sales.
        $importe = BigInteger::of($total)->multipliedBy($acumulado)->quotient($item->cantidad)->toInt() - $importeDevuelto;

        if ($acumulado === $item->cantidad) {
            $neto = Importes::aCentavos((string) $item->neto) - $netoDevuelto;
            $iva = Importes::aCentavos((string) $item->iva) - $ivaDevuelto;

            if ($neto >= 0 && $iva >= 0 && $neto + $iva === $importe) {
                return ['importe' => $importe, 'neto' => $neto, 'iva' => $iva];
            }
        }

        $desglose = $this->iva->desglosar(Importes::aDecimal($importe), (string) $item->alicuota);

        return [
            'importe' => $importe,
            'neto' => Importes::aCentavos($desglose['neto']),
            'iva' => Importes::aCentavos($desglose['iva']),
        ];
    }

    /**
     * Ítems a devolver con sus importes, en orden de `variante_id`, verificando las cantidades
     * contra lo vendido menos lo ya devuelto.
     *
     * @param  Collection<int, VentaItem>  $items  Ítems de la venta por id, con sus devoluciones.
     * @param  array<int|string, int>  $cantidades
     * @return list<array{item: VentaItem, cantidad: int, importe: int, neto: int, iva: int}>
     *
     * @throws DevolucionRechazada
     */
    private function lineas(Collection $items, array $cantidades): array
    {
        foreach ($cantidades as $itemId => $cantidad) {
            if (! $items->has((int) $itemId)) {
                throw new DevolucionRechazada('El producto no es de esta venta.', "cantidades.{$itemId}");
            }

            if ($cantidad < 0) {
                throw new DevolucionRechazada('La cantidad no puede ser negativa.', "cantidades.{$itemId}");
            }
        }

        $lineas = [];

        foreach ($items as $item) {
            $cantidad = (int) ($cantidades[$item->id] ?? 0);

            if ($cantidad === 0) {
                continue;
            }

            $devueltas = (int) $item->devolucionItems->sum('cantidad');
            $disponibles = $item->cantidad - $devueltas;

            if ($cantidad > $disponibles) {
                throw new DevolucionRechazada(
                    $disponibles === 0
                        ? 'Este producto ya se devolvió completo.'
                        : sprintf('Se pueden devolver como máximo %d %s de este producto.', $disponibles, $disponibles === 1 ? 'unidad' : 'unidades'),
                    "cantidades.{$item->id}",
                );
            }

            $lineas[] = [
                'item' => $item,
                'cantidad' => $cantidad,
                ...$this->importes(
                    $item,
                    $cantidad,
                    $devueltas,
                    $item->devolucionItems->sum(fn (DevolucionItem $devuelto) => Importes::aCentavos((string) $devuelto->importe)),
                    $item->devolucionItems->sum(fn (DevolucionItem $devuelto) => Importes::aCentavos((string) $devuelto->neto)),
                    $item->devolucionItems->sum(fn (DevolucionItem $devuelto) => Importes::aCentavos((string) $devuelto->iva)),
                ),
            ];
        }

        return $lineas;
    }

    /**
     * El reembolso exige permiso, que la venta se haya cobrado solo por caja, un medio activo
     * que pase por caja (con referencia si la requiere) y la caja abierta del usuario.
     *
     * @throws DevolucionRechazada
     * @throws CajaNoAbierta
     */
    private function verificarReembolso(Venta $venta, ?MedioPago $medio, ?string $referencia): CajaSesion
    {
        if (! Gate::allows(Permiso::VentasReembolsar->value)) {
            throw new DevolucionRechazada('No tenés permiso para reembolsar dinero: devolvé con saldo a favor.', 'resolucion');
        }

        $cobradaPorCuenta = $venta->pagos()->with('medioPago')->get()
            ->contains(fn (VentaPago $pago) => ! $pago->medioPago->tipo->admiteMovimientoManual());

        if ($cobradaPorCuenta) {
            throw new DevolucionRechazada('La venta se cobró con cuenta corriente o saldo a favor: la devolución se acredita en la cuenta del cliente.', 'resolucion');
        }

        if (! $medio || ! $medio->activo || ! $medio->tipo->admiteMovimientoManual()) {
            throw new DevolucionRechazada('Elegí un medio activo para el reembolso.', 'medio_pago_id');
        }

        if ($medio->requiere_referencia && $referencia === null) {
            throw new DevolucionRechazada("Indicá la referencia del reembolso con {$medio->nombre}.", 'referencia');
        }

        return $this->caja->sesionAbierta()
            ?? throw new CajaNoAbierta('No tenés una caja abierta en esta sucursal: abrila para reembolsar.');
    }
}
