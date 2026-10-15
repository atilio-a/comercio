<?php

namespace Tests\Feature\Ventas;

use App\Enums\Permiso;
use App\Enums\ResolucionDevolucion;
use App\Enums\TipoComprobante;
use App\Enums\TipoMovimientoCaja;
use App\Enums\TipoMovimientoStock;
use App\Exceptions\CajaNoAbierta;
use App\Exceptions\DevolucionRechazada;
use App\Models\Auditoria;
use App\Models\CajaMovimiento;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Devolucion;
use App\Models\DevolucionItem;
use App\Models\MovimientoStock;
use App\Models\Venta;
use App\Services\CuentasCorrientes;
use App\Services\Devoluciones;
use App\Services\Importes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\EscenarioVentas;
use Tests\TestCase;

class RegistrarDevolucionTest extends TestCase
{
    use EscenarioVentas, RefreshDatabase;

    private Devoluciones $devoluciones;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararEscenarioVentas();
        $this->abrirCaja($this->vendedorUser);
        $this->operarComo($this->vendedorUser);
        $this->devoluciones = app(Devoluciones::class);
    }

    public function test_partial_and_total_return_with_saldo_a_favor(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Ana Gomez']);

        $venta = $this->vender(
            [
                ['variante_id' => $this->negro37->id, 'cantidad' => 2],
                ['variante_id' => $this->media->id, 'cantidad' => 1],
            ],
            [$this->pago($this->efectivo, '35250')],
            $cliente,
        );

        $itemBota = $venta->items()->where('variante_id', $this->negro37->id)->first();
        $itemMedia = $venta->items()->where('variante_id', $this->media->id)->first();

        // 1. Devolución parcial: 1 de las 2 botas
        $devolucionParcial = $this->devoluciones->registrar(
            $venta,
            [$itemBota->id => 1],
            'Talle chico',
            ResolucionDevolucion::SaldoAFavor,
        );

        $this->assertSame('12100.00', (string) $devolucionParcial->importe);
        $this->assertSame('10000.00', (string) $devolucionParcial->neto);
        $this->assertSame('2100.00', (string) $devolucionParcial->iva);
        $this->assertSame('Talle chico', $devolucionParcial->motivo);
        $this->assertSame(ResolucionDevolucion::SaldoAFavor, $devolucionParcial->resolucion);
        $this->assertSame($this->vendedorUser->id, $devolucionParcial->user_id);
        $this->assertSame(self::DISPOSITIVO, $devolucionParcial->dispositivo);
        $this->assertCount(1, $devolucionParcial->items);

        // Cuenta corriente del cliente tiene ahora 12100 de saldo a favor
        $this->assertSame('12100.00', $cliente->fresh()->cuentaCorriente->saldoAFavor());

        // 2. Devolución restante (total de lo que queda): 1 bota y 1 media
        $devolucionRestante = $this->devoluciones->registrar(
            $venta,
            [
                $itemBota->id => 1,
                $itemMedia->id => 1,
            ],
            'Devolución del resto',
            ResolucionDevolucion::SaldoAFavor,
        );

        $this->assertSame('23150.00', (string) $devolucionRestante->importe);
        $this->assertCount(2, $devolucionRestante->items);

        // Total devuelto suma el total de la venta: 12100 + 23150 = 35250
        $totalDevuelto = Importes::aCentavos((string) $devolucionParcial->importe) + Importes::aCentavos((string) $devolucionRestante->importe);
        $this->assertSame(Importes::aCentavos((string) $venta->total), $totalDevuelto);

        // Saldo a favor total del cliente es 35250
        $this->assertSame('35250.00', $cliente->fresh()->cuentaCorriente->saldoAFavor());
    }

    public function test_cannot_return_more_than_sold_or_already_returned(): void
    {
        $cliente = Cliente::factory()->create();

        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 2]],
            [$this->pago($this->efectivo, '24200')],
            $cliente,
        );

        $itemBota = $venta->items()->first();

        // Intentar devolver 3 de 2
        try {
            $this->devoluciones->registrar(
                $venta,
                [$itemBota->id => 3],
                'Cambio',
                ResolucionDevolucion::SaldoAFavor,
            );
            $this->fail('Se esperaba DevolucionRechazada.');
        } catch (DevolucionRechazada $e) {
            $this->assertStringContainsString('Se pueden devolver como máximo 2 unidades', $e->getMessage());
            $this->assertSame("cantidades.{$itemBota->id}", $e->campo);
        }

        // Devolver 1 unidad
        $this->devoluciones->registrar(
            $venta,
            [$itemBota->id => 1],
            'Cambio 1',
            ResolucionDevolucion::SaldoAFavor,
        );

        // Intentar devolver 2 unidades más cuando solo queda 1
        try {
            $this->devoluciones->registrar(
                $venta,
                [$itemBota->id => 2],
                'Cambio 2',
                ResolucionDevolucion::SaldoAFavor,
            );
            $this->fail('Se esperaba DevolucionRechazada.');
        } catch (DevolucionRechazada $e) {
            $this->assertStringContainsString('Se pueden devolver como máximo 1 unidad', $e->getMessage());
        }

        // Devolver la última unidad disponible
        $this->devoluciones->registrar(
            $venta,
            [$itemBota->id => 1],
            'Cambio final',
            ResolucionDevolucion::SaldoAFavor,
        );

        // Intentar devolver cuando ya se devolvió completo
        try {
            $this->devoluciones->registrar(
                $venta,
                [$itemBota->id => 1],
                'Cambio extra',
                ResolucionDevolucion::SaldoAFavor,
            );
            $this->fail('Se esperaba DevolucionRechazada.');
        } catch (DevolucionRechazada $e) {
            $this->assertSame('Este producto ya se devolvió completo.', $e->getMessage());
        }

        // Intentar devolver sin cantidades
        try {
            $this->devoluciones->registrar(
                $venta,
                [$itemBota->id => 0],
                'Sin cantidad',
                ResolucionDevolucion::SaldoAFavor,
            );
            $this->fail('Se esperaba DevolucionRechazada.');
        } catch (DevolucionRechazada $e) {
            $this->assertSame('Elegí al menos un producto para devolver.', $e->getMessage());
        }

        // Intentar devolver producto inexistente en la venta
        try {
            $this->devoluciones->registrar(
                $venta,
                [99999 => 1],
                'Inexistente',
                ResolucionDevolucion::SaldoAFavor,
            );
            $this->fail('Se esperaba DevolucionRechazada.');
        } catch (DevolucionRechazada $e) {
            $this->assertSame('El producto no es de esta venta.', $e->getMessage());
        }
    }

    public function test_cannot_return_items_from_a_voided_sale(): void
    {
        $cliente = Cliente::factory()->create();

        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '12100')],
            $cliente,
        );

        $item = $venta->items()->first();

        // Anular la venta como gerente
        $this->actingAs($this->gerente);
        $this->post(route('ventas.anular', $venta), ['motivo' => 'Error']);

        $this->operarComo($this->vendedorUser);

        $this->expectException(DevolucionRechazada::class);
        $this->expectExceptionMessage("La venta N° {$venta->id} está anulada: no admite devoluciones.");

        $this->devoluciones->registrar(
            $venta,
            [$item->id => 1],
            'Intento de devolución',
            ResolucionDevolucion::SaldoAFavor,
        );
    }

    public function test_stock_reentry_records_movement_with_origin_devolucion(): void
    {
        $this->assertSame(5, $this->stockDe($this->negro37));

        $cliente = Cliente::factory()->create();

        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 2]],
            [$this->pago($this->efectivo, '24200')],
            $cliente,
        );

        $this->assertSame(3, $this->stockDe($this->negro37));
        $item = $venta->items()->first();

        $devolucion = $this->devoluciones->registrar(
            $venta,
            [$item->id => 1],
            'Defecto',
            ResolucionDevolucion::SaldoAFavor,
        );

        $this->assertSame(4, $this->stockDe($this->negro37));

        $movimiento = MovimientoStock::where('origen_type', Devolucion::class)
            ->where('origen_id', $devolucion->id)
            ->sole();

        $this->assertSame(TipoMovimientoStock::Ingreso, $movimiento->tipo);
        $this->assertSame(1, $movimiento->cantidad);
        $this->assertSame($this->negro37->id, $movimiento->variante_id);
        $this->assertSame($this->sucursal->id, $movimiento->sucursal_id);
        $this->assertTrue($movimiento->origen->is($devolucion));
    }

    public function test_saldo_a_favor_creates_account_without_credit_or_reduces_debt(): void
    {
        // 1. Cliente nuevo sin cuenta corriente
        $clienteNuevo = Cliente::factory()->create();
        $this->assertNull($clienteNuevo->cuentaCorriente);

        $venta1 = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '12100')],
            $clienteNuevo,
        );

        $this->devoluciones->registrar(
            $venta1,
            [$venta1->items()->first()->id => 1],
            'Saldo a favor',
            ResolucionDevolucion::SaldoAFavor,
        );

        $cuenta = $clienteNuevo->fresh()->cuentaCorriente;
        $this->assertNotNull($cuenta);
        $this->assertSame('0.00', (string) $cuenta->limite_credito);
        $this->assertSame('12100.00', $cuenta->saldoAFavor());
        $this->assertSame('0.00', $cuenta->deuda());

        // 2. Cliente con deuda existente por venta a cuenta corriente
        $clienteConDeuda = Cliente::factory()->create();
        $clienteConDeuda->cuentaCorriente()->create(['admite_credito' => true, 'limite_credito' => '50000.00']);

        // Venta a cuenta corriente de 2 botas = 24200 de deuda
        $ventaACuenta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 2]],
            [$this->pago($this->cuentaCorriente, '24200')],
            $clienteConDeuda,
        );

        $cuentaConDeuda = $clienteConDeuda->fresh()->cuentaCorriente;
        $this->assertSame('24200.00', $cuentaConDeuda->deuda());
        $this->assertSame('0.00', $cuentaConDeuda->saldoAFavor());

        // Devolvemos 1 bota (12100): la deuda baja a 24200 - 12100 = 12100
        $this->devoluciones->registrar(
            $ventaACuenta,
            [$ventaACuenta->items()->first()->id => 1],
            'Reducción de deuda',
            ResolucionDevolucion::SaldoAFavor,
        );

        $cuentaConDeuda->refresh();
        $this->assertSame('12100.00', $cuentaConDeuda->deuda());
        $this->assertSame('0.00', $cuentaConDeuda->saldoAFavor());
    }

    public function test_consumer_final_requires_customer_for_saldo_a_favor(): void
    {
        // Venta a consumidor final (sin cliente)
        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '12100')],
            null,
        );

        $item = $venta->items()->first();

        // Sin cliente -> rechazada
        try {
            $this->devoluciones->registrar(
                $venta,
                [$item->id => 1],
                'Cambio de talle',
                ResolucionDevolucion::SaldoAFavor,
                null,
            );
            $this->fail('Se esperaba DevolucionRechazada.');
        } catch (DevolucionRechazada $e) {
            $this->assertSame('cliente_id', $e->campo);
            $this->assertSame('La venta fue a consumidor final: elegí o da de alta el cliente que recibe el saldo a favor.', $e->getMessage());
        }

        // Pasando un cliente -> aceptada y acreditada
        $cliente = Cliente::factory()->create(['nombre' => 'Roberto']);

        $devolucion = $this->devoluciones->registrar(
            $venta,
            [$item->id => 1],
            'Cambio de talle',
            ResolucionDevolucion::SaldoAFavor,
            $cliente,
        );

        $this->assertSame($cliente->id, $devolucion->cliente_id);
        $this->assertSame('12100.00', $cliente->fresh()->cuentaCorriente->saldoAFavor());
    }

    public function test_refund_requires_permission_and_open_cash_register(): void
    {
        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '12100')],
        );
        $item = $venta->items()->first();

        // 1. Vendedor intenta reembolsar (no tiene permiso VentasReembolsar)
        try {
            $this->devoluciones->registrar(
                $venta,
                [$item->id => 1],
                'Reembolso',
                ResolucionDevolucion::Reembolso,
                medioReembolso: $this->efectivo,
            );
            $this->fail('Se esperaba DevolucionRechazada por falta de permiso.');
        } catch (DevolucionRechazada $e) {
            $this->assertSame('resolucion', $e->campo);
            $this->assertSame('No tenés permiso para reembolsar dinero: devolvé con saldo a favor.', $e->getMessage());
        }

        // 2. Gerente tiene permiso pero NO tiene caja abierta
        $this->operarComo($this->gerente);

        try {
            $this->devoluciones->registrar(
                $venta,
                [$item->id => 1],
                'Reembolso',
                ResolucionDevolucion::Reembolso,
                medioReembolso: $this->efectivo,
            );
            $this->fail('Se esperaba CajaNoAbierta.');
        } catch (CajaNoAbierta $e) {
            $this->assertSame('No tenés una caja abierta en esta sucursal: abrila para reembolsar.', $e->getMessage());
        }

        // 3. Gerente abre caja y reembolsa exitosamente
        $sesionGerente = $this->abrirCaja($this->gerente);

        $devolucion = $this->devoluciones->registrar(
            $venta,
            [$item->id => 1],
            'Reembolso en efectivo',
            ResolucionDevolucion::Reembolso,
            medioReembolso: $this->efectivo,
        );

        $this->assertSame(ResolucionDevolucion::Reembolso, $devolucion->resolucion);
        $this->assertSame($this->efectivo->id, $devolucion->medio_pago_id);
        $this->assertSame($sesionGerente->id, $devolucion->caja_sesion_id);

        // Movimiento de caja registrado con importe negativo
        $movCaja = CajaMovimiento::where('caja_sesion_id', $sesionGerente->id)
            ->where('tipo', TipoMovimientoCaja::Devolucion)
            ->sole();

        $this->assertSame('-12100.00', (string) $movCaja->importe);
        $this->assertSame($this->efectivo->id, $movCaja->medio_pago_id);
        $this->assertTrue($movCaja->origen->is($devolucion));
    }

    public function test_sale_paid_with_saldo_a_favor_or_cuenta_corriente_cannot_be_refunded(): void
    {
        $cliente = $this->clienteConSaldoAFavor('15000');
        $sesion = $this->abrirCaja($this->gerente);
        $this->operarComo($this->gerente);

        // Venta pagada en parte con saldo a favor
        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 2]],
            [
                $this->pago($this->saldoAFavor, '10000'),
                $this->pago($this->efectivo, '14200'),
            ],
            $cliente,
        );

        $item = $venta->items()->first();

        $this->expectException(DevolucionRechazada::class);
        $this->expectExceptionMessage('La venta se cobró con cuenta corriente o saldo a favor: la devolución se acredita en la cuenta del cliente.');

        $this->devoluciones->registrar(
            $venta,
            [$item->id => 1],
            'Intento de reembolso',
            ResolucionDevolucion::Reembolso,
            medioReembolso: $this->efectivo,
        );
    }

    public function test_prorated_amounts_with_discounts_and_surcharges_round_to_exact_cents(): void
    {
        $cliente = Cliente::factory()->create();

        // 3 pares de botas con $ 1000 de descuento general
        // Bruto: 3 * 12100 = 36300. Con descuento 1000 = 35300
        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 3]],
            [$this->pago($this->efectivo, '35300')],
            $cliente,
            descuentoGeneral: '1000',
            autorizador: $this->gerente,
        );

        $item = $venta->items()->sole();
        $this->assertSame('35300.00', (string) $item->total);

        // Devolver unidad 1
        $dev1 = $this->devoluciones->registrar(
            $venta,
            [$item->id => 1],
            'Unidad 1',
            ResolucionDevolucion::SaldoAFavor,
        );

        // Devolver unidad 2
        $dev2 = $this->devoluciones->registrar(
            $venta,
            [$item->id => 1],
            'Unidad 2',
            ResolucionDevolucion::SaldoAFavor,
        );

        // Devolver unidad 3
        $dev3 = $this->devoluciones->registrar(
            $venta,
            [$item->id => 1],
            'Unidad 3',
            ResolucionDevolucion::SaldoAFavor,
        );

        // Comprobar que en cada devolución importe = neto + iva
        foreach ([$dev1, $dev2, $dev3] as $dev) {
            $centavosImporte = Importes::aCentavos((string) $dev->importe);
            $centavosNeto = Importes::aCentavos((string) $dev->neto);
            $centavosIva = Importes::aCentavos((string) $dev->iva);
            $this->assertSame($centavosImporte, $centavosNeto + $centavosIva);
        }

        // Comprobar que la suma de las 3 devoluciones es exactamente el total del ítem (35300.00)
        $totalDevuelto = Importes::aCentavos((string) $dev1->importe)
            + Importes::aCentavos((string) $dev2->importe)
            + Importes::aCentavos((string) $dev3->importe);

        $this->assertSame(3530000, $totalDevuelto);

        $netoDevuelto = Importes::aCentavos((string) $dev1->neto)
            + Importes::aCentavos((string) $dev2->neto)
            + Importes::aCentavos((string) $dev3->neto);

        $this->assertSame(Importes::aCentavos((string) $item->neto), $netoDevuelto);

        $ivaDevuelto = Importes::aCentavos((string) $dev1->iva)
            + Importes::aCentavos((string) $dev2->iva)
            + Importes::aCentavos((string) $dev3->iva);

        $this->assertSame(Importes::aCentavos((string) $item->iva), $ivaDevuelto);
    }

    public function test_atomicity_failure_in_customer_account_rolls_back_everything(): void
    {
        $cliente = Cliente::factory()->create();

        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 2]],
            [$this->pago($this->efectivo, '24200')],
            $cliente,
        );

        $item = $venta->items()->first();
        $stockAntes = $this->stockDe($this->negro37);

        // Forzar falla en la acreditación de cuenta corriente
        $mockCuentas = $this->createMock(CuentasCorrientes::class);
        $mockCuentas->expects($this->once())
            ->method('acreditar')
            ->willThrowException(new RuntimeException('Error simulado al acreditar en CC'));

        $this->app->instance(CuentasCorrientes::class, $mockCuentas);

        $devoluciones = app(Devoluciones::class);

        try {
            $devoluciones->registrar(
                $venta,
                [$item->id => 1],
                'Test atomicidad',
                ResolucionDevolucion::SaldoAFavor,
            );
            $this->fail('Se esperaba RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertSame('Error simulado al acreditar en CC', $e->getMessage());
        }

        // Verificar que no se grabó nada
        $this->assertSame(0, Devolucion::count());
        $this->assertSame(0, DevolucionItem::count());
        $this->assertSame($stockAntes, $this->stockDe($this->negro37));
        $this->assertSame(0, MovimientoStock::where('origen_type', Devolucion::class)->count());
        $this->assertSame(0, Comprobante::where('tipo', TipoComprobante::NotaCreditoInterna)->count());

        // Al intentar de nuevo sin falla, la numeración de la nota de crédito debe empezar en 1
        $this->app->forgetInstance(CuentasCorrientes::class);

        $devolucionExitosa = app(Devoluciones::class)->registrar(
            $venta,
            [$item->id => 1],
            'Devolución posterior',
            ResolucionDevolucion::SaldoAFavor,
        );

        $this->assertSame(1, $devolucionExitosa->notaCredito->numero);
    }

    public function test_audit_log_recorded_for_both_resolutions(): void
    {
        $cliente = Cliente::factory()->create();

        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 2]],
            [$this->pago($this->efectivo, '24200')],
            $cliente,
        );
        $item = $venta->items()->first();

        // 1. Auditoría con saldo a favor
        $devSaldo = $this->devoluciones->registrar(
            $venta,
            [$item->id => 1],
            'Auditoría saldo a favor',
            ResolucionDevolucion::SaldoAFavor,
        );

        $auditoriaSaldo = Auditoria::where('accion', 'devoluciones.saldo_a_favor')->sole();
        $this->assertSame($this->vendedorUser->id, $auditoriaSaldo->user_id);
        $this->assertSame($devSaldo->id, $auditoriaSaldo->modelo_id);
        $this->assertSame('12100.00', $auditoriaSaldo->despues['importe']);
        $this->assertSame($venta->id, $auditoriaSaldo->despues['venta_id']);

        // 2. Auditoría con reembolso
        $this->abrirCaja($this->gerente);
        $this->operarComo($this->gerente);

        $devReembolso = app(Devoluciones::class)->registrar(
            $venta,
            [$item->id => 1],
            'Auditoría reembolso',
            ResolucionDevolucion::Reembolso,
            medioReembolso: $this->efectivo,
        );

        $auditoriaReembolso = Auditoria::where('accion', 'devoluciones.reembolso')->sole();
        $this->assertSame($this->gerente->id, $auditoriaReembolso->user_id);
        $this->assertSame($devReembolso->id, $auditoriaReembolso->modelo_id);
        $this->assertSame('12100.00', $auditoriaReembolso->despues['importe']);
        $this->assertSame($this->efectivo->id, $auditoriaReembolso->despues['medio_pago_id']);
    }
}
