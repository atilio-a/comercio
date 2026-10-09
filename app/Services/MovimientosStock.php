<?php

namespace App\Services;

use App\Enums\Parametro;
use App\Enums\TipoMovimientoStock;
use App\Exceptions\StockInsuficiente;
use App\Exceptions\TransferenciaNoHabilitada;
use App\Http\Middleware\IdentificarDispositivo;
use App\Models\MovimientoStock;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\Variante;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Única puerta para modificar el stock (STK-01, STK-02, VEN-09).
 *
 * Contrato para los módulos que la usan (ventas, compras, cambios):
 * - Llamar siempre dentro de una transacción abierta por quien llama, que define qué más
 *   se graba junto con el movimiento (venta, caja, etc.). Fuera de una transacción lanza
 *   LogicException.
 * - Cada llamada bloquea la fila de stock de la variante y sucursal (`lockForUpdate`).
 *   Si una operación mueve varias variantes, debe procesarlas en orden ascendente de
 *   `variante_id` para evitar deadlocks entre operaciones simultáneas.
 * - Un movimiento que deja el stock por debajo de 0 lanza {@see StockInsuficiente} si el
 *   parámetro "Permitir venta con stock negativo" está deshabilitado; si está habilitado,
 *   se registra y se audita como `stock.negativo`.
 * - Las transferencias lanzan {@see TransferenciaNoHabilitada}.
 * - Los movimientos son inmutables: para corregir uno, registrar otro (con `reversaDe`
 *   cuando sea una reversión).
 */
class MovimientosStock
{
    public function __construct(
        private Parametros $parametros,
        private RegistradorAuditoria $auditoria,
        private Request $request,
    ) {}

    /**
     * Registra un movimiento y actualiza el saldo.
     *
     * @param  int  $cantidad  Con signo: positiva suma, negativa resta. Ingreso > 0, egreso < 0.
     * @param  CarbonInterface|null  $fecha  Fecha de negocio; hoy si es null.
     *
     * @throws StockInsuficiente
     * @throws TransferenciaNoHabilitada
     */
    public function registrar(
        Variante $variante,
        Sucursal $sucursal,
        TipoMovimientoStock $tipo,
        int $cantidad,
        ?string $motivo = null,
        ?Model $origen = null,
        ?CarbonInterface $fecha = null,
        ?MovimientoStock $reversaDe = null,
    ): MovimientoStock {
        $this->exigirTransaccion();

        if ($tipo === TipoMovimientoStock::Transferencia) {
            throw new TransferenciaNoHabilitada;
        }

        if ($cantidad === 0) {
            throw new InvalidArgumentException('Un movimiento de stock no puede tener cantidad 0.');
        }

        if ($tipo->signo() !== 0 && $tipo->signo() !== ($cantidad <=> 0)) {
            throw new InvalidArgumentException("La cantidad de un {$tipo->value} debe ser ".($tipo->signo() > 0 ? 'positiva.' : 'negativa.'));
        }

        $stock = $this->bloquear($variante, $sucursal);
        $anterior = $stock->cantidad;
        $saldo = $anterior + $cantidad;
        $quedaNegativo = $cantidad < 0 && $saldo < 0;

        if ($quedaNegativo && ! $this->parametros->habilitado(Parametro::StockNegativo)) {
            throw new StockInsuficiente($variante, $anterior);
        }

        $movimiento = MovimientoStock::create([
            'variante_id' => $variante->id,
            'sucursal_id' => $sucursal->id,
            'fecha' => ($fecha ?? now())->toDateString(),
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'saldo' => $saldo,
            'motivo' => $motivo,
            'origen_type' => $origen?->getMorphClass(),
            'origen_id' => $origen?->getKey(),
            'reversa_de_id' => $reversaDe?->id,
            'user_id' => Auth::id(),
            'dispositivo' => $this->request->attributes->get(IdentificarDispositivo::ATRIBUTO),
        ]);

        $stock->forceFill(['cantidad' => $saldo])->save();

        if ($quedaNegativo) {
            $this->auditoria->registrar('stock.negativo', $movimiento, ['cantidad' => $anterior], [
                'variante_id' => $variante->id,
                'sucursal_id' => $sucursal->id,
                'tipo' => $tipo->value,
                'cantidad' => $cantidad,
                'saldo' => $saldo,
            ]);
        }

        return $movimiento;
    }

    /**
     * Lleva el stock a la cantidad contada registrando un ajuste por la diferencia.
     * Devuelve null si no hay diferencia.
     */
    public function ajustarAConteo(Variante $variante, Sucursal $sucursal, int $contado, string $motivo, CarbonInterface $fecha): ?MovimientoStock
    {
        $this->exigirTransaccion();

        if ($contado < 0) {
            throw new InvalidArgumentException('La cantidad contada no puede ser negativa.');
        }

        $anterior = $this->bloquear($variante, $sucursal)->cantidad;
        $diferencia = $contado - $anterior;

        if ($diferencia === 0) {
            return null;
        }

        $movimiento = $this->registrar($variante, $sucursal, TipoMovimientoStock::Ajuste, $diferencia, $motivo, fecha: $fecha);

        $this->auditoria->registrar('stock.ajuste', $movimiento, ['cantidad' => $anterior], [
            'cantidad' => $contado,
            'variante_id' => $variante->id,
            'sucursal_id' => $sucursal->id,
            'motivo' => $motivo,
        ]);

        return $movimiento;
    }

    /**
     * Define el stock mínimo. No genera movimientos.
     */
    public function actualizarMinimo(Variante $variante, Sucursal $sucursal, int $minimo): void
    {
        $this->exigirTransaccion();

        if ($minimo < 0) {
            throw new InvalidArgumentException('El stock mínimo no puede ser negativo.');
        }

        $stock = $this->bloquear($variante, $sucursal);

        if ($stock->minimo === $minimo) {
            return;
        }

        $anterior = $stock->minimo;
        $stock->forceFill(['minimo' => $minimo])->save();

        $this->auditoria->registrar('stock.minimo_actualizado', $stock, ['minimo' => $anterior], ['minimo' => $minimo]);
    }

    /**
     * Asegura que exista la fila de stock y la bloquea hasta el final de la transacción.
     */
    private function bloquear(Variante $variante, Sucursal $sucursal): Stock
    {
        Stock::query()->insertOrIgnore([
            'variante_id' => $variante->id,
            'sucursal_id' => $sucursal->id,
            'cantidad' => 0,
            'minimo' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Stock::query()
            ->where('variante_id', $variante->id)
            ->where('sucursal_id', $sucursal->id)
            ->lockForUpdate()
            ->sole();
    }

    private function exigirTransaccion(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Los movimientos de stock se registran dentro de una transacción.');
        }
    }
}
