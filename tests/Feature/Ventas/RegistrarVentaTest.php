<?php

namespace Tests\Feature\Ventas;

use App\Enums\EstadoVenta;
use App\Enums\Parametro;
use App\Enums\TipoComprobante;
use App\Enums\TipoMovimientoCaja;
use App\Enums\TipoMovimientoCuenta;
use App\Enums\TipoMovimientoStock;
use App\Exceptions\CajaNoAbierta;
use App\Exceptions\CreditoNoDisponible;
use App\Exceptions\StockInsuficiente;
use App\Exceptions\VentaRechazada;
use App\Models\Auditoria;
use App\Models\CajaMovimiento;
use App\Models\CcMovimiento;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\CuentaCorriente;
use App\Models\MovimientoStock;
use App\Models\PlanCuota;
use App\Models\PuntoVenta;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\VentaPago;
use App\Services\Comprobantes\EmisorComprobante;
use App\Services\Comprobantes\EmisorInterno;
use App\Services\Comprobantes\EmisorNoDisponible;
use App\Services\Comprobantes\NumeradorComprobantes;
use App\Services\Parametros;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Support\EscenarioVentas;
use Tests\TestCase;
use Throwable;

class RegistrarVentaTest extends TestCase
{
    use EscenarioVentas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararEscenarioVentas();
        $this->abrirCaja($this->vendedorUser);
        $this->operarComo($this->vendedorUser);
    }

    public function test_a_cash_sale_records_items_stock_cash_and_the_receipt_in_one_operation(): void
    {
        $venta = $this->vender([['variante_id' => $this->negro37->id, 'cantidad' => 2]], [$this->pago($this->efectivo, '24200')]);

        $venta->refresh();
        $this->assertSame(EstadoVenta::Confirmada, $venta->estado);
        $this->assertSame($this->sucursal->id, $venta->sucursal_id);
        $this->assertSame(now()->toDateString(), $venta->fecha->toDateString());
        $this->assertNull($venta->cliente_id);
        $this->assertSame($this->vendedor->id, $venta->vendedor_id);
        $this->assertSame($this->vendedorUser->id, $venta->user_id);
        $this->assertSame(self::DISPOSITIVO, $venta->dispositivo);
        $this->assertSame('24200.00', $venta->total);
        $this->assertSame('20000.00', $venta->subtotal_neto);
        $this->assertSame('4200.00', $venta->iva);
        $this->assertNotNull($venta->caja_sesion_id);

        $item = VentaItem::sole();
        $this->assertSame(2, $item->cantidad);
        $this->assertSame('12100.00', $item->precio_final);
        $this->assertSame('10000.00', $item->precio_neto);
        $this->assertSame('21.00', $item->alicuota);
        $this->assertSame('6000.00', $item->costo_unitario);
        $this->assertNull($item->descuento_autorizado_por);

        $this->assertSame(3, $this->stockDe($this->negro37));
        $egreso = MovimientoStock::sole();
        $this->assertSame(TipoMovimientoStock::Egreso, $egreso->tipo);
        $this->assertSame(-2, $egreso->cantidad);
        $this->assertTrue($egreso->origen->is($venta));

        $cobro = CajaMovimiento::sole();
        $this->assertSame(TipoMovimientoCaja::Venta, $cobro->tipo);
        $this->assertSame('24200.00', $cobro->importe);
        $this->assertSame($venta->caja_sesion_id, $cobro->caja_sesion_id);
        $this->assertTrue($cobro->origen->is($venta));

        $comprobante = $venta->comprobante;
        $this->assertSame(TipoComprobante::VentaInterna, $comprobante->tipo);
        $this->assertSame(1, $comprobante->numero);
        $this->assertSame($this->puntoVenta->id, $comprobante->punto_venta_id);
        $this->assertSame('interno', $comprobante->emisor);
        $this->assertNull($comprobante->cae);
        $this->assertSame('Documento no válido como factura', $comprobante->datos_json['leyenda']);
        $this->assertSame('00001-00000001', $comprobante->numeroCompleto());
    }

    public function test_the_item_keeps_the_cost_of_the_moment(): void
    {
        $this->vender([['variante_id' => $this->negro37->id, 'cantidad' => 1]], [$this->pago($this->efectivo, '12100')]);

        $this->negro37->update(['costo' => '9999.00', 'precio_final' => '15000.00']);

        $item = VentaItem::sole();
        $this->assertSame('6000.00', $item->costo_unitario);
        $this->assertSame('12100.00', $item->precio_final);
    }

    public function test_combined_payments_record_one_cash_movement_per_medium(): void
    {
        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1], ['variante_id' => $this->media->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '10000'), $this->pago($this->debito, '13150', ['referencia' => 'Cupón 4512'])],
        );

        $this->assertSame('23150.00', $venta->fresh()->total);
        $this->assertSame(2, VentaPago::count());
        $this->assertEqualsCanonicalizing(['10000.00', '13150.00'], CajaMovimiento::pluck('importe')->all());
        $this->assertSame('Cupón 4512', CajaMovimiento::where('medio_pago_id', $this->debito->id)->value('referencia'));
    }

    public function test_the_credit_card_surcharge_goes_into_the_total_and_each_items_vat(): void
    {
        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1], ['variante_id' => $this->media->id, 'cantidad' => 1]],
            [$this->pago($this->credito, '23150', ['plan_cuota_id' => $this->tresCuotas->id, 'referencia' => '1234', 'lote' => '77', 'cupon' => '0099'])],
        );

        $venta->refresh();
        $this->assertSame('2315.00', $venta->recargo);
        $this->assertSame('25465.00', $venta->total);
        $this->assertSame('22000.00', $venta->subtotal_neto);
        $this->assertSame('3465.00', $venta->iva);

        $pago = VentaPago::sole();
        $this->assertSame(3, $pago->cuotas);
        $this->assertSame('10.00', $pago->recargo_pct);
        $this->assertSame('23150.00', $pago->importe);
        $this->assertSame('2315.00', $pago->recargo);
        $this->assertSame(['77', '0099'], [$pago->lote, $pago->cupon]);
        $this->assertSame('25465.00', CajaMovimiento::sole()->importe);

        $items = $venta->items()->orderBy('id')->get();
        $this->assertSame(['1210.00', '1105.00'], $items->pluck('recargo')->all());
        $this->assertSame(['2310.00', '1155.00'], $items->pluck('iva')->all());
    }

    public function test_payments_that_do_not_add_up_are_rejected_without_recording_anything(): void
    {
        $this->assertVentaRechazada('pagos', fn () => $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '10000')],
        ));

        $this->assertNadaGrabado();
    }

    public function test_insufficient_stock_is_rejected_when_negative_stock_is_disabled(): void
    {
        try {
            $this->vender([['variante_id' => $this->media->id, 'cantidad' => 6]], [$this->pago($this->efectivo, '66300')]);
            $this->fail('Se esperaba StockInsuficiente.');
        } catch (StockInsuficiente $e) {
            $this->assertSame(5, $e->disponible);
        }

        $this->assertSame(5, $this->stockDe($this->media));
        $this->assertNadaGrabado();
    }

    public function test_negative_stock_is_allowed_and_audited_when_the_parameter_is_enabled(): void
    {
        app(Parametros::class)->guardar(Parametro::StockNegativo, true);

        $venta = $this->vender([['variante_id' => $this->media->id, 'cantidad' => 6]], [$this->pago($this->efectivo, '66300')]);

        $this->assertSame(-1, $this->stockDe($this->media));
        $this->assertSame(EstadoVenta::Confirmada, $venta->fresh()->estado);
        $this->assertTrue(Auditoria::where('accion', 'stock.negativo')->exists());
    }

    public function test_a_closed_cash_register_rejects_cash_and_rolls_back_the_stock(): void
    {
        $this->operarComo($this->cajero);

        $this->expectException(CajaNoAbierta::class);

        try {
            $this->vender([['variante_id' => $this->negro37->id, 'cantidad' => 2]], [$this->pago($this->efectivo, '24200')]);
        } finally {
            $this->assertSame(5, $this->stockDe($this->negro37));
            $this->assertNadaGrabado();
        }
    }

    public function test_a_failure_after_the_cash_movement_rolls_back_stock_and_cash(): void
    {
        $cliente = Cliente::factory()->create();

        try {
            $this->vender(
                [['variante_id' => $this->negro37->id, 'cantidad' => 2]],
                [$this->pago($this->efectivo, '4200'), $this->pago($this->cuentaCorriente, '20000')],
                $cliente,
            );
            $this->fail('Se esperaba CreditoNoDisponible.');
        } catch (CreditoNoDisponible) {
        }

        $this->assertSame(5, $this->stockDe($this->negro37));
        $this->assertSame(0, CajaMovimiento::count());
        $this->assertSame(0, CuentaCorriente::count());
        $this->assertNadaGrabado();
    }

    public function test_paying_with_credit_in_favour_debits_the_customer_account(): void
    {
        $cliente = $this->clienteConSaldoAFavor('8000');

        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->saldoAFavor, '5000'), $this->pago($this->efectivo, '7100')],
            $cliente,
        );

        $debito = CcMovimiento::where('tipo', TipoMovimientoCuenta::Debe->value)->sole();
        $this->assertSame('5000.00', $debito->importe);
        $this->assertTrue($debito->origen->is($venta));
        $this->assertSame('3000.00', $cliente->cuentaCorriente->fresh()->saldoAFavor());
        $this->assertSame($cliente->id, $venta->fresh()->cliente_id);
    }

    public function test_a_sale_paid_only_with_credit_in_favour_needs_no_cash_register(): void
    {
        $cliente = $this->clienteConSaldoAFavor('20000');
        $this->operarComo($this->cajero);

        $venta = $this->vender([['variante_id' => $this->negro37->id, 'cantidad' => 1]], [$this->pago($this->saldoAFavor, '12100')], $cliente);

        $this->assertNull($venta->fresh()->caja_sesion_id);
        $this->assertSame(0, CajaMovimiento::count());
        $this->assertSame('7900.00', $cliente->cuentaCorriente->fresh()->saldoAFavor());
    }

    public function test_credit_in_favour_cannot_exceed_what_the_customer_has(): void
    {
        $cliente = $this->clienteConSaldoAFavor('3000');

        $excepcion = $this->assertVentaRechazada('pagos.0.importe', fn () => $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->saldoAFavor, '5000'), $this->pago($this->efectivo, '7100')],
            $cliente,
        ));

        $this->assertSame('El cliente tiene $ 3.000,00 de saldo a favor.', $excepcion->getMessage());
        $this->assertSame('3000.00', $cliente->cuentaCorriente->fresh()->saldoAFavor());
        $this->assertNadaGrabado();
    }

    public function test_credit_in_favour_never_creates_debt_even_with_credit_enabled(): void
    {
        $cliente = $this->clienteConSaldoAFavor('1000');
        $cliente->cuentaCorriente->update(['admite_credito' => true, 'limite_credito' => '100000.00']);

        $this->assertVentaRechazada('pagos.0.importe', fn () => $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->saldoAFavor, '12100')],
            $cliente,
        ));
    }

    public function test_customer_account_without_credit_is_rejected_without_debt(): void
    {
        $cliente = Cliente::factory()->create();

        $this->expectException(CreditoNoDisponible::class);

        try {
            $this->vender([['variante_id' => $this->negro37->id, 'cantidad' => 1]], [$this->pago($this->cuentaCorriente, '12100')], $cliente);
        } finally {
            $this->assertSame(0, CcMovimiento::count());
            $this->assertNadaGrabado();
        }
    }

    public function test_customer_account_with_credit_records_the_debt(): void
    {
        $cliente = Cliente::factory()->create();
        CuentaCorriente::factory()->for($cliente)->conCredito('50000.00')->create();

        $this->vender([['variante_id' => $this->negro37->id, 'cantidad' => 1]], [$this->pago($this->cuentaCorriente, '12100')], $cliente);

        $this->assertSame('12100.00', $cliente->cuentaCorriente->fresh()->deuda());
    }

    public function test_customer_account_and_credit_in_favour_require_a_customer(): void
    {
        $this->assertVentaRechazada('pagos.0.medio_pago_id', fn () => $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->saldoAFavor, '12100')],
        ));
    }

    public function test_the_instalment_plan_must_belong_to_the_medium_and_be_active(): void
    {
        $otroPlan = PlanCuota::factory()->create(['cuotas' => 6]);
        $items = [['variante_id' => $this->negro37->id, 'cantidad' => 1]];

        $this->assertVentaRechazada('pagos.0.plan_cuota_id', fn () => $this->vender($items, [$this->pago($this->credito, '12100', ['plan_cuota_id' => $otroPlan->id, 'referencia' => '1'])]));
        $this->assertVentaRechazada('pagos.0.plan_cuota_id', fn () => $this->vender($items, [$this->pago($this->credito, '12100', ['referencia' => '1'])]));
        $this->assertVentaRechazada('pagos.0.plan_cuota_id', fn () => $this->vender($items, [$this->pago($this->efectivo, '12100', ['plan_cuota_id' => $this->unaCuota->id])]));

        $this->tresCuotas->update(['activo' => false]);
        $this->assertVentaRechazada('pagos.0.plan_cuota_id', fn () => $this->vender($items, [$this->pago($this->credito, '12100', ['plan_cuota_id' => $this->tresCuotas->id, 'referencia' => '1'])]));
    }

    public function test_a_medium_that_requires_a_reference_demands_it(): void
    {
        $this->assertVentaRechazada('pagos.0.referencia', fn () => $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->debito, '12100')],
        ));
    }

    public function test_inactive_variants_and_mediums_are_rejected(): void
    {
        $this->negro38->update(['activo' => false]);
        $this->assertVentaRechazada('items.0.variante_id', fn () => $this->vender([['variante_id' => $this->negro38->id, 'cantidad' => 1]], [$this->pago($this->efectivo, '12100')]));

        $this->efectivo->update(['activo' => false]);
        $this->assertVentaRechazada('pagos.0.medio_pago_id', fn () => $this->vender([['variante_id' => $this->negro37->id, 'cantidad' => 1]], [$this->pago($this->efectivo, '12100')]));
    }

    public function test_discounts_require_an_authorizer_with_permission(): void
    {
        $items = [['variante_id' => $this->negro37->id, 'cantidad' => 1, 'descuento' => '100']];
        $pagos = [$this->pago($this->efectivo, '12000')];

        $this->assertVentaRechazada('autorizacion', fn () => $this->vender($items, $pagos));
        $this->assertVentaRechazada('autorizacion', fn () => $this->vender($items, $pagos, autorizador: $this->cajero));
        $this->assertNadaGrabado();

        $venta = $this->vender($items, $pagos, descuentoGeneral: '0', autorizador: $this->gerente);

        $this->assertNull($venta->fresh()->descuento_autorizado_por);
        $this->assertSame($this->gerente->id, VentaItem::sole()->descuento_autorizado_por);
        $this->assertSame('100.00', $venta->fresh()->descuento);

        $auditoria = Auditoria::where('accion', 'ventas.descuento_autorizado')->sole();
        $this->assertSame($this->gerente->id, $auditoria->despues['autorizado_por']);
        $this->assertSame([['variante_id' => $this->negro37->id, 'descuento' => '100.00']], $auditoria->despues['items']);
    }

    public function test_the_general_discount_records_its_authorizer_on_the_sale(): void
    {
        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1], ['variante_id' => $this->negro38->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '23200')],
            descuentoGeneral: '1000',
            autorizador: $this->gerente,
        );

        $venta->refresh();
        $this->assertSame($this->gerente->id, $venta->descuento_autorizado_por);
        $this->assertSame('1000.00', $venta->descuento);
        $this->assertSame(['500.00', '500.00'], $venta->items()->orderBy('id')->pluck('descuento_venta')->all());
        $this->assertSame([null, null], $venta->items()->pluck('descuento_autorizado_por')->all());
    }

    public function test_numbering_is_correlative_per_point_of_sale_and_type_without_gaps(): void
    {
        $segundo = PuntoVenta::factory()->create(['sucursal_id' => $this->sucursal->id, 'numero' => 2]);
        $items = [['variante_id' => $this->negro37->id, 'cantidad' => 1]];
        $pagos = [$this->pago($this->efectivo, '12100')];

        $this->vender($items, $pagos);
        $this->assertVentaRechazada('pagos', fn () => $this->vender($items, [$this->pago($this->efectivo, '1')]));
        $this->vender($items, $pagos);

        $this->puntoVenta = $segundo;
        $this->vender($items, $pagos);

        $this->assertSame([1, 2], Comprobante::where('punto_venta_id', PuntoVenta::where('numero', 1)->value('id'))->orderBy('numero')->pluck('numero')->all());
        $this->assertSame([1], Comprobante::where('punto_venta_id', $segundo->id)->pluck('numero')->all());
    }

    public function test_the_database_rejects_a_duplicated_receipt_number(): void
    {
        $venta = $this->vender([['variante_id' => $this->negro37->id, 'cantidad' => 1]], [$this->pago($this->efectivo, '12100')]);
        $original = $venta->comprobante;

        $this->expectException(QueryException::class);

        Comprobante::create([
            'sucursal_id' => $original->sucursal_id,
            'venta_id' => $venta->id,
            'tipo' => $original->tipo,
            'punto_venta_id' => $original->punto_venta_id,
            'numero' => $original->numero,
            'fecha' => now()->toDateString(),
            'emisor' => 'interno',
            'datos_json' => [],
        ]);
    }

    public function test_a_point_of_sale_of_another_branch_or_inactive_is_rejected(): void
    {
        $items = [['variante_id' => $this->negro37->id, 'cantidad' => 1]];
        $pagos = [$this->pago($this->efectivo, '12100')];

        $this->puntoVenta = PuntoVenta::factory()->create(['numero' => 9]);
        $this->assertVentaRechazada('punto_venta_id', fn () => $this->vender($items, $pagos));

        $this->puntoVenta = PuntoVenta::factory()->inactivo()->create(['sucursal_id' => $this->sucursal->id, 'numero' => 10]);
        $this->assertVentaRechazada('punto_venta_id', fn () => $this->vender($items, $pagos));
    }

    public function test_electronic_invoicing_enabled_without_a_fiscal_issuer_rejects_the_sale(): void
    {
        $this->assertInstanceOf(EmisorInterno::class, app(EmisorComprobante::class));

        app(Parametros::class)->guardar(Parametro::FacturacionElectronica, true);
        $this->assertInstanceOf(EmisorNoDisponible::class, app(EmisorComprobante::class));

        $excepcion = $this->assertVentaRechazada('items', fn () => $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '12100')],
        ));

        $this->assertSame(EmisorNoDisponible::MENSAJE, $excepcion->getMessage());
        $this->assertSame(5, $this->stockDe($this->negro37));
        $this->assertNadaGrabado();
    }

    public function test_sales_items_payments_and_receipts_are_immutable(): void
    {
        $venta = $this->vender([['variante_id' => $this->negro37->id, 'cantidad' => 1]], [$this->pago($this->efectivo, '12100')]);

        foreach ([
            fn () => $venta->fresh()->update(['total' => '1.00']),
            fn () => $venta->fresh()->forceFill(['estado' => EstadoVenta::Anulada, 'total' => '1.00'])->save(),
            fn () => $venta->fresh()->delete(),
            fn () => VentaItem::sole()->update(['cantidad' => 3]),
            fn () => VentaItem::sole()->delete(),
            fn () => VentaPago::sole()->update(['importe' => '1.00']),
            fn () => VentaPago::sole()->delete(),
            fn () => Comprobante::sole()->update(['numero' => 99]),
            fn () => Comprobante::sole()->delete(),
        ] as $operacion) {
            try {
                $operacion();
                $this->fail('Se esperaba LogicException.');
            } catch (LogicException) {
            }
        }

        $this->assertSame('12100.00', $venta->fresh()->total);
    }

    public function test_the_numbering_service_requires_a_transaction(): void
    {
        DB::partialMock()->shouldReceive('transactionLevel')->andReturn(0);

        $this->expectException(LogicException::class);

        app(NumeradorComprobantes::class)->siguiente($this->puntoVenta, TipoComprobante::VentaInterna);
    }

    private function assertVentaRechazada(string $campo, callable $operacion): VentaRechazada
    {
        try {
            $operacion();
        } catch (VentaRechazada $e) {
            $this->assertSame($campo, $e->campo, $e->getMessage());

            return $e;
        } catch (Throwable $e) {
            $this->fail('Se esperaba VentaRechazada y se lanzó '.$e::class.': '.$e->getMessage());
        }

        $this->fail('Se esperaba VentaRechazada en '.$campo);
    }

    private function assertNadaGrabado(): void
    {
        $this->assertSame(0, Venta::count());
        $this->assertSame(0, VentaItem::count());
        $this->assertSame(0, VentaPago::count());
        $this->assertSame(0, Comprobante::count());
        $this->assertSame(0, MovimientoStock::count());
    }
}
