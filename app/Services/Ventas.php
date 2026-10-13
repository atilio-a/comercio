<?php

namespace App\Services;

use App\Enums\EstadoVenta;
use App\Enums\Permiso;
use App\Enums\TipoMedioPago;
use App\Enums\TipoMovimientoCaja;
use App\Enums\TipoMovimientoStock;
use App\Exceptions\CajaNoAbierta;
use App\Exceptions\CreditoNoDisponible;
use App\Exceptions\StockInsuficiente;
use App\Exceptions\VentaRechazada;
use App\Http\Middleware\IdentificarDispositivo;
use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Models\CcMovimiento;
use App\Models\Cliente;
use App\Models\MedioPago;
use App\Models\MovimientoStock;
use App\Models\PlanCuota;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Variante;
use App\Models\Vendedor;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\VentaPago;
use App\Services\Comprobantes\EmisorComprobante;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Registro y anulación de ventas (VEN-01, VEN-02, VEN-05, VEN-08, VEN-09).
 *
 * `registrar()` graba en una sola transacción la venta, sus ítems y pagos, el egreso de stock
 * ({@see MovimientosStock}), los cobros en caja ({@see MovimientosCaja}) y en cuenta corriente
 * ({@see CuentasCorrientes}) y el comprobante ({@see EmisorComprobante}). Bloquea en el orden
 * acordado: stock por `variante_id` ascendente, sesión de caja, cuenta corriente y, al final,
 * el punto de venta para numerar. Si algo falla no queda nada grabado.
 *
 * Reglas de cobro:
 * - Los importes salen de {@see CalculadoraVenta} con el precio final de la variante (nunca del
 *   navegador); los pagos (sin recargo) deben sumar el total sin recargo.
 * - Todo medio salvo cuenta corriente y saldo a favor pasa por la caja abierta del usuario en
 *   la sucursal, aunque no impacte en caja física (así el arqueo y la conciliación lo ven).
 * - Cuenta corriente y saldo a favor exigen cliente y se debitan en su cuenta; el saldo a favor
 *   solo cubre hasta lo que el cliente tiene a favor.
 * - Todo descuento exige un autorizante con `ventas.autorizar_descuentos`; la venta y los
 *   ítems guardan quién autorizó y se audita `ventas.descuento_autorizado`.
 *
 * Errores: {@see VentaRechazada} (con el campo del formulario), {@see StockInsuficiente},
 * {@see CajaNoAbierta} y {@see CreditoNoDisponible}.
 */
class Ventas
{
    public function __construct(
        private CalculadoraVenta $calculadora,
        private MovimientosStock $stock,
        private MovimientosCaja $caja,
        private CuentasCorrientes $cuentas,
        private EmisorComprobante $emisor,
        private RegistradorAuditoria $auditoria,
        private Request $request,
    ) {}

    /**
     * @param  array<int|string, array{variante_id: int, cantidad: int, descuento: string}>  $items  Clave = posición en el formulario.
     * @param  array<int|string, array{medio_pago_id: int, plan_cuota_id: ?int, importe: string, referencia?: ?string, lote?: ?string, cupon?: ?string}>  $pagos
     * @param  User|null  $autorizador  Quien autoriza los descuentos; obligatorio si hay alguno.
     *
     * @throws VentaRechazada
     * @throws StockInsuficiente
     * @throws CajaNoAbierta
     * @throws CreditoNoDisponible
     */
    public function registrar(
        Sucursal $sucursal,
        PuntoVenta $puntoVenta,
        Vendedor $vendedor,
        ?Cliente $cliente,
        array $items,
        string $descuentoGeneral,
        array $pagos,
        ?User $autorizador = null,
    ): Venta {
        $this->emisor->verificarDisponible();

        if ((int) $puntoVenta->sucursal_id !== (int) $sucursal->id || ! $puntoVenta->activo) {
            throw new VentaRechazada('El punto de venta no está disponible en esta sucursal.', 'punto_venta_id');
        }

        if (! $vendedor->activo) {
            throw new VentaRechazada('El vendedor no está activo.', 'vendedor_id');
        }

        return DB::transaction(function () use ($sucursal, $puntoVenta, $vendedor, $cliente, $items, $descuentoGeneral, $pagos, $autorizador) {
            $variantes = $this->variantes($items);
            $pagos = $this->prepararPagos($pagos, $cliente);
            $calculo = $this->calculadora->calcular($this->lineas($items, $variantes), $descuentoGeneral, $pagos);

            $hayDescuento = Importes::aCentavos($calculo['descuento']) > 0;

            if ($hayDescuento) {
                $this->verificarAutorizador($autorizador);
            }

            $sesion = $this->sesionDeCaja($pagos);

            $venta = Venta::create([
                'sucursal_id' => $sucursal->id,
                'fecha' => now()->toDateString(),
                'cliente_id' => $cliente?->id,
                'vendedor_id' => $vendedor->id,
                'caja_sesion_id' => $sesion?->id,
                'punto_venta_id' => $puntoVenta->id,
                'estado' => EstadoVenta::Confirmada,
                'subtotal_neto' => $calculo['subtotal_neto'],
                'iva' => $calculo['iva'],
                'descuento' => $calculo['descuento'],
                'recargo' => $calculo['recargo'],
                'total' => $calculo['total'],
                'descuento_autorizado_por' => Importes::aCentavos($calculo['descuento_general']) > 0 ? $autorizador?->id : null,
                'user_id' => Auth::id(),
                'dispositivo' => $this->request->attributes->get(IdentificarDispositivo::ATRIBUTO),
            ]);

            foreach ($items as $posicion => $item) {
                $variante = $variantes->get($item['variante_id']);
                $linea = $calculo['lineas'][$posicion];

                VentaItem::create([
                    'venta_id' => $venta->id,
                    'variante_id' => $variante->id,
                    'cantidad' => $item['cantidad'],
                    'precio_final' => (string) $variante->precio_final,
                    'precio_neto' => $linea['precio_neto'],
                    'alicuota' => (string) $variante->producto->alicuotaIva->porcentaje,
                    'descuento' => $linea['descuento'],
                    'descuento_autorizado_por' => Importes::aCentavos($linea['descuento']) > 0 ? $autorizador?->id : null,
                    'descuento_venta' => $linea['descuento_venta'],
                    'recargo' => $linea['recargo'],
                    'total' => $linea['total'],
                    'neto' => $linea['neto'],
                    'iva' => $linea['iva'],
                    'costo_unitario' => (string) $variante->costo,
                ]);
            }

            foreach ($pagos as $posicion => $pago) {
                VentaPago::create([
                    'venta_id' => $venta->id,
                    'medio_pago_id' => $pago['medio']->id,
                    'plan_cuota_id' => $pago['plan']?->id,
                    'cuotas' => $pago['plan']?->cuotas,
                    'recargo_pct' => $pago['recargo_pct'],
                    'importe' => $calculo['pagos'][$posicion]['importe'],
                    'recargo' => $calculo['pagos'][$posicion]['recargo'],
                    'referencia' => $pago['referencia'],
                    'lote' => $pago['lote'],
                    'cupon' => $pago['cupon'],
                ]);
            }

            $concepto = "Venta N° {$venta->id}";

            // Ascending variant id: the lock order every stock operation must follow.
            foreach (collect($items)->sortBy('variante_id') as $item) {
                $this->stock->registrar($variantes->get($item['variante_id']), $sucursal, TipoMovimientoStock::Egreso, -$item['cantidad'], $concepto, $venta);
            }

            foreach ($pagos as $posicion => $pago) {
                if ($this->pasaPorCaja($pago['medio'])) {
                    $this->caja->registrar(
                        TipoMovimientoCaja::Venta,
                        $pago['medio'],
                        $calculo['pagos'][$posicion]['cobrado'],
                        $concepto,
                        $venta,
                        $sesion,
                        $pago['referencia'] ?? $pago['cupon'],
                    );
                }
            }

            foreach ($pagos as $posicion => $pago) {
                if (! $this->pasaPorCaja($pago['medio'])) {
                    $this->debitarCuenta($cliente, $pago['medio'], $calculo['pagos'][$posicion]['cobrado'], $concepto, $venta, $posicion);
                }
            }

            $this->emisor->emitirVenta($venta);

            if ($hayDescuento) {
                $this->auditoria->registrar('ventas.descuento_autorizado', $venta, null, [
                    'autorizado_por' => $autorizador->id,
                    'descuento_general' => $calculo['descuento_general'],
                    'items' => collect($items)
                        ->map(fn (array $item, int|string $posicion) => ['variante_id' => $item['variante_id'], 'descuento' => $calculo['lineas'][$posicion]['descuento']])
                        ->filter(fn (array $item) => Importes::aCentavos($item['descuento']) > 0)
                        ->values()
                        ->all(),
                    'total' => $calculo['total'],
                ]);
            }

            return $venta;
        });
    }

    /**
     * Totales del carrito con las mismas reglas que el registro, sin exigir que los pagos
     * sumen el total (informa lo pendiente). No graba nada.
     *
     * @param  array<int|string, array{variante_id: int, cantidad: int, descuento: string}>  $items
     * @param  array<int|string, array{medio_pago_id: int, plan_cuota_id: ?int, importe: string}>  $pagos
     * @return array<string, mixed> El resultado de {@see CalculadoraVenta::calcular()}.
     *
     * @throws VentaRechazada
     */
    public function previsualizar(array $items, string $descuentoGeneral, array $pagos): array
    {
        $variantes = $this->variantes($items);

        return $this->calculadora->calcular(
            $this->lineas($items, $variantes),
            $descuentoGeneral,
            $this->prepararPagos($pagos, null, exigirDatosDeCobro: false),
            exigirPagos: false,
        );
    }

    /**
     * Anula la venta por reversión (VEN-05): reingresa el stock, contraasienta caja y cuenta
     * corriente, emite el comprobante de anulación y marca la venta. No borra nada.
     *
     * @throws VentaRechazada si ya está anulada o su sesión de caja está cerrada.
     * @throws StockInsuficiente
     */
    public function anular(Venta $venta, string $motivo): Venta
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new VentaRechazada('Indicá el motivo de la anulación.', 'motivo');
        }

        $this->emisor->verificarDisponible();

        return DB::transaction(function () use ($venta, $motivo) {
            $venta = Venta::query()->lockForUpdate()->findOrFail($venta->id);

            if ($venta->estaAnulada()) {
                throw new VentaRechazada("La venta N° {$venta->id} ya está anulada.", 'motivo');
            }

            if ($venta->caja_sesion_id && ! CajaSesion::query()->findOrFail($venta->caja_sesion_id)->estaAbierta()) {
                throw new VentaRechazada('La caja de la venta ya se cerró: no se puede anular. Registrá un cambio o devolución.', 'motivo');
            }

            $concepto = "Anulación de la venta N° {$venta->id}: {$motivo}";
            $sucursal = $venta->sucursal;

            $egresos = MovimientoStock::query()
                ->whereMorphedTo('origen', $venta)
                ->where('tipo', TipoMovimientoStock::Egreso->value)
                ->whereNull('reversa_de_id')
                ->get()
                ->keyBy('variante_id');

            $items = $venta->items()->with(['variante.producto', 'variante.color'])->orderBy('variante_id')->get();

            foreach ($items as $item) {
                $this->stock->registrar(
                    $item->variante,
                    $sucursal,
                    TipoMovimientoStock::Ingreso,
                    $item->cantidad,
                    $concepto,
                    $venta,
                    reversaDe: $egresos->get($item->variante_id),
                );
            }

            CajaMovimiento::query()
                ->whereMorphedTo('origen', $venta)
                ->whereNull('reversa_de_id')
                ->orderBy('id')
                ->get()
                ->each(fn (CajaMovimiento $movimiento) => $this->caja->contraasentar($movimiento, $concepto));

            CcMovimiento::query()
                ->whereMorphedTo('origen', $venta)
                ->whereNull('reversa_de_id')
                ->orderBy('id')
                ->get()
                ->each(fn (CcMovimiento $movimiento) => $this->cuentas->contraasentar($movimiento, $concepto));

            $this->emisor->emitirAnulacion($venta, $venta->comprobante()->firstOrFail(), $motivo);

            $venta->forceFill([
                'estado' => EstadoVenta::Anulada,
                'anulada_por' => Auth::id(),
                'anulada_at' => now(),
                'motivo_anulacion' => $motivo,
            ])->save();

            $this->auditoria->registrar('ventas.anulada', $venta, ['estado' => EstadoVenta::Confirmada->value], [
                'estado' => EstadoVenta::Anulada->value,
                'motivo' => $motivo,
                'total' => (string) $venta->total,
                'items' => $items->map(fn (VentaItem $item) => [
                    'variante_id' => $item->variante_id,
                    'cantidad' => $item->cantidad,
                ])->all(),
            ]);

            return $venta;
        });
    }

    /**
     * Todo medio salvo cuenta corriente y saldo a favor pasa por la caja (D4 del diseño).
     */
    public function pasaPorCaja(MedioPago $medio): bool
    {
        return $medio->tipo->admiteMovimientoManual();
    }

    /**
     * Variantes vendibles de los ítems, por id, con producto, alícuota y color.
     *
     * @param  array<int|string, array{variante_id: int, cantidad: int, descuento: string}>  $items
     * @return Collection<int, Variante>
     *
     * @throws VentaRechazada
     */
    private function variantes(array $items): Collection
    {
        if ($items === []) {
            throw new VentaRechazada('Agregá al menos un producto.', 'items');
        }

        $variantes = Variante::query()
            ->vendibles()
            ->with(['producto.alicuotaIva', 'color'])
            ->whereIn('variantes.id', array_column($items, 'variante_id'))
            ->get()
            ->keyBy('id');

        $vistas = [];

        foreach ($items as $posicion => $item) {
            if (! $variantes->has($item['variante_id'])) {
                throw new VentaRechazada('La variante no existe o está inactiva.', "items.{$posicion}.variante_id");
            }

            if (isset($vistas[$item['variante_id']])) {
                throw new VentaRechazada('La variante está repetida en la venta.', "items.{$posicion}.variante_id");
            }

            if ($item['cantidad'] < 1) {
                throw new VentaRechazada('La cantidad debe ser al menos 1.', "items.{$posicion}.cantidad");
            }

            $vistas[$item['variante_id']] = true;
        }

        return $variantes;
    }

    /**
     * @param  array<int|string, array{variante_id: int, cantidad: int, descuento: string}>  $items
     * @param  Collection<int, Variante>  $variantes
     * @return array<int|string, array{precio_final: string, alicuota: string, cantidad: int, descuento: string}>
     */
    private function lineas(array $items, Collection $variantes): array
    {
        return array_map(function (array $item) use ($variantes) {
            $variante = $variantes->get($item['variante_id']);

            return [
                'precio_final' => (string) $variante->precio_final,
                'alicuota' => (string) $variante->producto->alicuotaIva->porcentaje,
                'cantidad' => $item['cantidad'],
                'descuento' => $item['descuento'],
            ];
        }, $items);
    }

    /**
     * Medios y planes de los pagos, verificados.
     *
     * @param  array<int|string, array{medio_pago_id: int, plan_cuota_id: ?int, importe: string, referencia?: ?string, lote?: ?string, cupon?: ?string}>  $pagos
     * @return array<int|string, array{medio: MedioPago, plan: ?PlanCuota, importe: string, recargo_pct: string, referencia: ?string, lote: ?string, cupon: ?string}>
     *
     * @throws VentaRechazada
     */
    private function prepararPagos(array $pagos, ?Cliente $cliente, bool $exigirDatosDeCobro = true): array
    {
        $medios = MedioPago::query()->whereIn('id', array_column($pagos, 'medio_pago_id'))->get()->keyBy('id');
        $planes = PlanCuota::query()->whereIn('id', array_filter(array_column($pagos, 'plan_cuota_id')))->get()->keyBy('id');
        $preparados = [];

        foreach ($pagos as $posicion => $pago) {
            /** @var MedioPago|null $medio */
            $medio = $medios->get($pago['medio_pago_id']);

            if (! $medio || ! $medio->activo) {
                throw new VentaRechazada('Elegí un medio de pago activo.', "pagos.{$posicion}.medio_pago_id");
            }

            $plan = $this->plan($medio, $pago['plan_cuota_id'] ?? null, $planes, $posicion);
            $referencia = $this->limpiar($pago['referencia'] ?? null);
            $cupon = $this->limpiar($pago['cupon'] ?? null);

            if ($exigirDatosDeCobro && ! $this->pasaPorCaja($medio) && ! $cliente) {
                throw new VentaRechazada("Elegí el cliente para cobrar con {$medio->nombre}.", "pagos.{$posicion}.medio_pago_id");
            }

            if ($exigirDatosDeCobro && $medio->requiere_referencia && ($referencia ?? $cupon) === null) {
                throw new VentaRechazada("Indicá la referencia del pago con {$medio->nombre}.", "pagos.{$posicion}.referencia");
            }

            $preparados[$posicion] = [
                'medio' => $medio,
                'plan' => $plan,
                'importe' => $pago['importe'],
                'recargo_pct' => $plan ? (string) $plan->recargo_pct : '0.00',
                'referencia' => $referencia,
                'lote' => $this->limpiar($pago['lote'] ?? null),
                'cupon' => $cupon,
            ];
        }

        return $preparados;
    }

    /**
     * Plan de cuotas del pago: obligatorio si el medio admite planes y tiene alguno activo;
     * debe ser del medio y estar activo.
     *
     * @param  Collection<int, PlanCuota>  $planes
     *
     * @throws VentaRechazada
     */
    private function plan(MedioPago $medio, ?int $planId, Collection $planes, int|string $posicion): ?PlanCuota
    {
        $campo = "pagos.{$posicion}.plan_cuota_id";

        if (! $medio->tipo->admitePlanes()) {
            if ($planId !== null) {
                throw new VentaRechazada("{$medio->nombre} no tiene planes de cuotas.", $campo);
            }

            return null;
        }

        if ($planId === null) {
            if ($medio->planes()->where('activo', true)->exists()) {
                throw new VentaRechazada('Elegí el plan de cuotas.', $campo);
            }

            return null;
        }

        /** @var PlanCuota|null $plan */
        $plan = $planes->get($planId);

        if (! $plan || (int) $plan->medio_pago_id !== (int) $medio->id || ! $plan->activo) {
            throw new VentaRechazada("Elegí un plan de cuotas activo de {$medio->nombre}.", $campo);
        }

        return $plan;
    }

    /**
     * Sesión abierta del usuario si algún pago pasa por caja; null si ninguno.
     *
     * @param  array<int|string, array{medio: MedioPago}>  $pagos
     *
     * @throws CajaNoAbierta
     */
    private function sesionDeCaja(array $pagos): ?CajaSesion
    {
        $usaCaja = collect($pagos)->contains(fn (array $pago) => $this->pasaPorCaja($pago['medio']));

        if (! $usaCaja) {
            return null;
        }

        return $this->caja->sesionAbierta()
            ?? throw new CajaNoAbierta('No tenés una caja abierta en esta sucursal: abrila para cobrar.');
    }

    /**
     * @throws VentaRechazada
     */
    private function verificarAutorizador(?User $autorizador): void
    {
        if (! $autorizador || ! $autorizador->activo || ! Gate::forUser($autorizador)->allows(Permiso::VentasAutorizarDescuentos->value)) {
            throw new VentaRechazada('Los descuentos requieren la autorización de un usuario con permiso.', 'autorizacion');
        }
    }

    /**
     * Débito en la cuenta corriente del cliente. El saldo a favor solo cubre lo que hay a favor.
     *
     * @throws VentaRechazada
     * @throws CreditoNoDisponible
     */
    private function debitarCuenta(Cliente $cliente, MedioPago $medio, string $importe, string $concepto, Venta $venta, int|string $posicion): void
    {
        if ($medio->tipo === TipoMedioPago::SaldoAFavor) {
            $disponible = $this->cuentas->cuentaDe($cliente)->saldoAFavor();

            if (Importes::aCentavos($importe) > Importes::aCentavos($disponible)) {
                throw new VentaRechazada(
                    sprintf('El cliente tiene $ %s de saldo a favor.', Formato::importe($disponible)),
                    "pagos.{$posicion}.importe",
                );
            }
        }

        $this->cuentas->debitar($cliente, $importe, $concepto, $venta);
    }

    private function limpiar(?string $texto): ?string
    {
        $texto = trim((string) $texto);

        return $texto === '' ? null : $texto;
    }
}
