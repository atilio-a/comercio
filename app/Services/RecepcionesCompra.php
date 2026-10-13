<?php

namespace App\Services;

use App\Enums\TipoMovimientoStock;
use App\Exceptions\CompraRechazada;
use App\Exceptions\StockInsuficiente;
use App\Http\Middleware\IdentificarDispositivo;
use App\Models\MovimientoStock;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use App\Models\Proveedor;
use App\Models\Recepcion;
use App\Models\RecepcionItem;
use App\Models\Sucursal;
use App\Models\Variante;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Recepción de mercadería y su anulación (COM-02).
 *
 * - `registrar()` crea la recepción y sus ítems, da ingreso al stock con
 *   {@see MovimientosStock} (origen la recepción, en orden ascendente de variante),
 *   actualiza lo recibido y el estado de la orden y, si se pide, el costo de la variante.
 * - `anular()` revierte cada ingreso con un egreso (`reversaDe`), descuenta lo recibido de la
 *   orden y marca la recepción como anulada.
 *
 * Ambos abren su transacción y bloquean primero la orden (y en la anulación, antes, la
 * recepción) y después las filas de stock. Las reglas que dependen de lo ya recibido se
 * verifican con la orden bloqueada, así dos recepciones simultáneas no superan lo pedido.
 */
class RecepcionesCompra
{
    public function __construct(
        private MovimientosStock $stock,
        private RegistradorAuditoria $auditoria,
        private Request $request,
    ) {}

    /**
     * @param  array<int|string, array{variante_id: int, cantidad: int, costo: string, actualizar_costo: bool}>  $items  Claves = posición en el formulario.
     *
     * @throws CompraRechazada
     * @throws StockInsuficiente
     */
    public function registrar(
        Sucursal $sucursal,
        Proveedor $proveedor,
        ?OrdenCompra $orden,
        CarbonInterface $fecha,
        ?string $referencia,
        ?string $observaciones,
        array $items,
    ): Recepcion {
        return DB::transaction(function () use ($sucursal, $proveedor, $orden, $fecha, $referencia, $observaciones, $items) {
            $itemsOrden = collect();

            if ($orden) {
                $orden = OrdenCompra::query()->whereKey($orden->id)->lockForUpdate()->sole();
                $itemsOrden = $this->verificarContraOrden($orden, $sucursal, $items);
                $proveedor = $orden->proveedor;
            }

            $recepcion = Recepcion::create([
                'sucursal_id' => $sucursal->id,
                'proveedor_id' => $proveedor->id,
                'orden_compra_id' => $orden?->id,
                'fecha' => $fecha->toDateString(),
                'referencia' => $referencia,
                'observaciones' => $observaciones,
                'user_id' => Auth::id(),
                'dispositivo' => $this->request->attributes->get(IdentificarDispositivo::ATRIBUTO),
            ]);

            $porVariante = collect($items)->keyBy('variante_id');
            $variantes = Variante::query()
                ->with(['producto:id,nombre', 'color:id,nombre'])
                ->whereIn('id', $porVariante->keys())
                ->orderBy('id')
                ->get();

            // Ascending variant id: the lock order every stock operation must follow.
            foreach ($variantes as $variante) {
                $item = $porVariante->get($variante->id);
                /** @var OrdenCompraItem|null $itemOrden */
                $itemOrden = $itemsOrden->get($variante->id);

                RecepcionItem::create([
                    'recepcion_id' => $recepcion->id,
                    'variante_id' => $variante->id,
                    'orden_compra_item_id' => $itemOrden?->id,
                    'cantidad' => $item['cantidad'],
                    'costo' => Importes::normalizar($item['costo']),
                    'actualiza_costo' => $item['actualizar_costo'],
                ]);

                $this->stock->registrar(
                    $variante,
                    $sucursal,
                    TipoMovimientoStock::Ingreso,
                    $item['cantidad'],
                    "Recepción N° {$recepcion->id}",
                    $recepcion,
                    $fecha,
                );

                if ($itemOrden) {
                    $itemOrden->cantidad_recibida += $item['cantidad'];
                    $itemOrden->save();
                }

                if ($item['actualizar_costo']) {
                    $this->actualizarCosto($variante, $item['costo'], $recepcion);
                }
            }

            if ($orden) {
                $orden->setRelation('items', $itemsOrden->values());
                $orden->update(['estado' => $orden->estadoSegunRecepciones()]);
            }

            return $recepcion;
        });
    }

    /**
     * Anula la recepción: egresos que revierten sus ingresos y descuento de lo recibido.
     * No revierte costos actualizados.
     *
     * @throws CompraRechazada
     * @throws StockInsuficiente si el stock negativo está deshabilitado y no alcanza.
     */
    public function anular(Recepcion $recepcion, string $motivo): Recepcion
    {
        return DB::transaction(function () use ($recepcion, $motivo) {
            $recepcion = Recepcion::query()->whereKey($recepcion->id)->lockForUpdate()->sole();

            if ($recepcion->estaAnulada()) {
                throw new CompraRechazada("La recepción N° {$recepcion->id} ya está anulada.", 'motivo');
            }

            $orden = $recepcion->orden_compra_id
                ? OrdenCompra::query()->whereKey($recepcion->orden_compra_id)->lockForUpdate()->sole()
                : null;

            $items = $recepcion->items()
                ->with(['variante.producto:id,nombre', 'variante.color:id,nombre'])
                ->orderBy('variante_id')
                ->get();

            $ingresos = MovimientoStock::query()
                ->whereMorphedTo('origen', $recepcion)
                ->where('tipo', TipoMovimientoStock::Ingreso)
                ->whereNull('reversa_de_id')
                ->get()
                ->keyBy('variante_id');

            $sucursal = $recepcion->sucursal;

            foreach ($items as $item) {
                $this->stock->registrar(
                    $item->variante,
                    $sucursal,
                    TipoMovimientoStock::Egreso,
                    -$item->cantidad,
                    "Anulación de la recepción N° {$recepcion->id}: {$motivo}",
                    $recepcion,
                    reversaDe: $ingresos->get($item->variante_id),
                );
            }

            if ($orden) {
                $itemsOrden = $orden->items()->get();

                foreach ($itemsOrden as $itemOrden) {
                    $recibido = $items->where('orden_compra_item_id', $itemOrden->id)->sum('cantidad');

                    if ($recibido > 0) {
                        $itemOrden->cantidad_recibida = max(0, $itemOrden->cantidad_recibida - $recibido);
                        $itemOrden->save();
                    }
                }

                $orden->setRelation('items', $itemsOrden);
                $orden->update(['estado' => $orden->estadoSegunRecepciones()]);
            }

            $recepcion->forceFill([
                'anulada_at' => now(),
                'anulada_por_id' => Auth::id(),
                'motivo_anulacion' => $motivo,
            ])->save();

            $this->auditoria->registrar('compras.recepcion.anulada', $recepcion, null, [
                'motivo' => $motivo,
                'orden_compra_id' => $orden?->id,
                'items' => $items->map(fn (RecepcionItem $item) => [
                    'variante_id' => $item->variante_id,
                    'cantidad' => $item->cantidad,
                ])->all(),
            ]);

            return $recepcion;
        });
    }

    /**
     * Ítems de la orden por variante, verificando estado, sucursal, variantes y pendientes.
     *
     * @param  array<int|string, array{variante_id: int, cantidad: int, costo: string, actualizar_costo: bool}>  $items
     * @return Collection<int, OrdenCompraItem>
     *
     * @throws CompraRechazada
     */
    private function verificarContraOrden(OrdenCompra $orden, Sucursal $sucursal, array $items): Collection
    {
        if ((int) $orden->sucursal_id !== (int) $sucursal->id) {
            throw new CompraRechazada('La orden es de otra sucursal.', 'orden_compra_id');
        }

        if (! $orden->estado->recibible()) {
            throw new CompraRechazada(
                "La orden N° {$orden->numero} está ".mb_strtolower($orden->estado->etiqueta()).': no admite recepciones.',
                'orden_compra_id',
            );
        }

        $itemsOrden = $orden->items()->get()->keyBy('variante_id');

        foreach ($items as $posicion => $item) {
            $itemOrden = $itemsOrden->get($item['variante_id']);

            if (! $itemOrden) {
                throw new CompraRechazada('La variante no está en la orden. Registrala como recepción sin orden.', "items.{$posicion}.variante_id");
            }

            if ($item['cantidad'] > $itemOrden->pendiente()) {
                throw new CompraRechazada(
                    "Supera lo pendiente de la orden ({$itemOrden->pendiente()}). Registrá el excedente como recepción sin orden.",
                    "items.{$posicion}.cantidad",
                );
            }
        }

        return $itemsOrden;
    }

    private function actualizarCosto(Variante $variante, string $costo, Recepcion $recepcion): void
    {
        $nuevo = Importes::normalizar($costo);
        $anterior = $variante->costo;

        if (Importes::aCentavos($anterior) === Importes::aCentavos($nuevo)) {
            return;
        }

        $variante->forceFill(['costo' => $nuevo])->save();

        $this->auditoria->registrar('variante.costo_actualizado', $variante, ['costo' => $anterior], [
            'costo' => $nuevo,
            'recepcion_id' => $recepcion->id,
        ]);
    }
}
