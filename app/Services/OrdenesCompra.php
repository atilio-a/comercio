<?php

namespace App\Services;

use App\Enums\EstadoOrdenCompra;
use App\Exceptions\CompraRechazada;
use App\Models\OrdenCompra;
use App\Models\Proveedor;
use App\Models\Sucursal;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Alta, edición, emisión y anulación de órdenes de compra (COM-01).
 *
 * Cada método abre su transacción, bloquea la orden (`lockForUpdate`) y verifica el estado
 * con la fila bloqueada; si la operación no corresponde lanza {@see CompraRechazada}.
 * Las cantidades recibidas solo las cambia {@see RecepcionesCompra}.
 */
class OrdenesCompra
{
    public function __construct(private RegistradorAuditoria $auditoria) {}

    /**
     * Crea la orden en borrador con el siguiente número de la sucursal.
     *
     * @param  list<array{variante_id: int, cantidad: int, costo: string}>  $items
     */
    public function crear(Sucursal $sucursal, Proveedor $proveedor, CarbonInterface $fecha, ?string $observaciones, array $items): OrdenCompra
    {
        return DB::transaction(function () use ($sucursal, $proveedor, $fecha, $observaciones, $items) {
            // Serializes numbering within the branch; the unique index backs it up.
            Sucursal::query()->whereKey($sucursal->id)->lockForUpdate()->sole();
            $numero = (int) OrdenCompra::query()->where('sucursal_id', $sucursal->id)->max('numero') + 1;

            $orden = OrdenCompra::create([
                'sucursal_id' => $sucursal->id,
                'proveedor_id' => $proveedor->id,
                'numero' => $numero,
                'fecha' => $fecha->toDateString(),
                'estado' => EstadoOrdenCompra::Borrador,
                'observaciones' => $observaciones,
                'user_id' => Auth::id(),
            ]);

            $this->guardarItems($orden, $items);

            $this->auditoria->registrar('compras.orden.creada', $orden, null, [
                'numero' => $numero,
                'proveedor_id' => $proveedor->id,
                'items' => $items,
            ]);

            return $orden;
        });
    }

    /**
     * Reemplaza datos e ítems de un borrador.
     *
     * @param  list<array{variante_id: int, cantidad: int, costo: string}>  $items
     *
     * @throws CompraRechazada
     */
    public function actualizar(OrdenCompra $orden, Proveedor $proveedor, CarbonInterface $fecha, ?string $observaciones, array $items): OrdenCompra
    {
        return DB::transaction(function () use ($orden, $proveedor, $fecha, $observaciones, $items) {
            $orden = $this->bloquear($orden);

            if (! $orden->estado->editable()) {
                throw new CompraRechazada("La orden N° {$orden->numero} está {$this->estado($orden)}: solo se edita un borrador.", 'orden');
            }

            $antes = [
                'proveedor_id' => $orden->proveedor_id,
                'fecha' => $orden->fecha->toDateString(),
                'observaciones' => $orden->observaciones,
                'items' => $orden->items()->orderBy('id')->get(['variante_id', 'cantidad_pedida', 'costo'])
                    ->map(fn ($item) => ['variante_id' => $item->variante_id, 'cantidad' => $item->cantidad_pedida, 'costo' => $item->costo])
                    ->all(),
            ];

            $orden->update([
                'proveedor_id' => $proveedor->id,
                'fecha' => $fecha->toDateString(),
                'observaciones' => $observaciones,
            ]);

            $orden->items()->delete();
            $this->guardarItems($orden, $items);

            $this->auditoria->registrar('compras.orden.actualizada', $orden, $antes, [
                'proveedor_id' => $proveedor->id,
                'fecha' => $fecha->toDateString(),
                'observaciones' => $observaciones,
                'items' => $items,
            ]);

            return $orden;
        });
    }

    /**
     * Pasa un borrador a emitida: desde ahí admite recepciones y no se edita.
     *
     * @throws CompraRechazada
     */
    public function emitir(OrdenCompra $orden): OrdenCompra
    {
        return DB::transaction(function () use ($orden) {
            $orden = $this->bloquear($orden);

            if ($orden->estado !== EstadoOrdenCompra::Borrador) {
                throw new CompraRechazada("La orden N° {$orden->numero} ya está {$this->estado($orden)}.", 'orden');
            }

            if (! $orden->items()->exists()) {
                throw new CompraRechazada('La orden no tiene ítems.', 'orden');
            }

            $orden->update(['estado' => EstadoOrdenCompra::Emitida]);

            $this->auditoria->registrar('compras.orden.emitida', $orden, ['estado' => EstadoOrdenCompra::Borrador->value], ['estado' => EstadoOrdenCompra::Emitida->value]);

            return $orden;
        });
    }

    /**
     * Anula una orden sin mercadería recibida (borrador o emitida).
     *
     * @throws CompraRechazada
     */
    public function anular(OrdenCompra $orden, string $motivo): OrdenCompra
    {
        return DB::transaction(function () use ($orden, $motivo) {
            $orden = $this->bloquear($orden);

            if (! $orden->estado->anulable() || $orden->items()->where('cantidad_recibida', '>', 0)->exists()) {
                throw new CompraRechazada(
                    "La orden N° {$orden->numero} está {$this->estado($orden)}: tiene mercadería recibida o ya fue anulada. Para corregir, anulá primero sus recepciones.",
                    'motivo',
                );
            }

            $anterior = $orden->estado;
            $orden->update(['estado' => EstadoOrdenCompra::Anulada]);

            $this->auditoria->registrar('compras.orden.anulada', $orden, ['estado' => $anterior->value], [
                'estado' => EstadoOrdenCompra::Anulada->value,
                'motivo' => $motivo,
            ]);

            return $orden;
        });
    }

    /**
     * @param  list<array{variante_id: int, cantidad: int, costo: string}>  $items
     */
    private function guardarItems(OrdenCompra $orden, array $items): void
    {
        foreach ($items as $item) {
            $orden->items()->create([
                'variante_id' => $item['variante_id'],
                'cantidad_pedida' => $item['cantidad'],
                'cantidad_recibida' => 0,
                'costo' => Importes::normalizar($item['costo']),
            ]);
        }
    }

    private function bloquear(OrdenCompra $orden): OrdenCompra
    {
        return OrdenCompra::query()->whereKey($orden->id)->lockForUpdate()->sole();
    }

    private function estado(OrdenCompra $orden): string
    {
        return mb_strtolower($orden->estado->etiqueta());
    }
}
