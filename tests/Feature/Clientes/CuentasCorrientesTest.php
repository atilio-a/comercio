<?php

namespace Tests\Feature\Clientes;

use App\Enums\TipoMovimientoCuenta;
use App\Exceptions\CreditoNoDisponible;
use App\Http\Middleware\IdentificarDispositivo;
use App\Models\Auditoria;
use App\Models\CcMovimiento;
use App\Models\Cliente;
use App\Models\CuentaCorriente;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CuentasCorrientes;
use App\Services\SucursalActiva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class CuentasCorrientesTest extends TestCase
{
    use RefreshDatabase;

    private const DISPOSITIVO = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';

    private Sucursal $sucursal;

    private User $user;

    private Cliente $cliente;

    private CuentasCorrientes $cuentas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::factory()->create();
        $this->user = User::factory()->conSucursal($this->sucursal)->create();
        $this->cliente = Cliente::factory()->create();

        $this->actingAs($this->user);
        app(SucursalActiva::class)->establecer($this->sucursal);
        request()->attributes->set(IdentificarDispositivo::ATRIBUTO, self::DISPOSITIVO);
        $this->cuentas = app(CuentasCorrientes::class);
    }

    public function test_the_account_is_created_without_credit_on_first_use(): void
    {
        $this->assertNull($this->cliente->cuentaCorriente);

        $cuenta = DB::transaction(fn () => $this->cuentas->cuentaDe($this->cliente));

        $this->assertFalse($cuenta->admite_credito);
        $this->assertSame('0.00', $cuenta->limite_credito);
        $this->assertFalse($cuenta->bloqueada);
        $this->assertSame('0.00', $cuenta->saldo);

        $otra = DB::transaction(fn () => $this->cuentas->cuentaDe($this->cliente));
        $this->assertSame($cuenta->id, $otra->id);
        $this->assertSame(1, CuentaCorriente::count());
    }

    public function test_crediting_records_a_haber_with_user_device_branch_and_balance(): void
    {
        $origen = Cliente::factory()->create();

        $movimiento = DB::transaction(fn () => $this->cuentas->acreditar($this->cliente, '8000', 'Devolución ticket 15', $origen));

        $movimiento->refresh();
        $this->assertSame(TipoMovimientoCuenta::Haber, $movimiento->tipo);
        $this->assertSame('8000.00', $movimiento->importe);
        $this->assertSame('-8000.00', $movimiento->saldo);
        $this->assertSame('Devolución ticket 15', $movimiento->concepto);
        $this->assertSame($this->sucursal->id, $movimiento->sucursal_id);
        $this->assertSame($this->user->id, $movimiento->user_id);
        $this->assertSame(self::DISPOSITIVO, $movimiento->dispositivo);
        $this->assertSame(now()->toDateString(), $movimiento->fecha->toDateString());
        $this->assertTrue($movimiento->origen->is($origen));

        $cuenta = $this->cliente->fresh()->cuentaCorriente;
        $this->assertFalse($cuenta->admite_credito);
        $this->assertSame('-8000.00', $cuenta->saldo);
        $this->assertSame('8000.00', $cuenta->saldoAFavor());
        $this->assertSame('0.00', $cuenta->deuda());
    }

    public function test_a_debit_can_consume_the_credit_balance(): void
    {
        DB::transaction(fn () => $this->cuentas->acreditar($this->cliente, '8000.00', 'Devolución'));

        $movimiento = DB::transaction(fn () => $this->cuentas->debitar($this->cliente, '5000.00', 'Pago con saldo a favor'));

        $this->assertSame(TipoMovimientoCuenta::Debe, $movimiento->tipo);
        $this->assertSame('-3000.00', $movimiento->fresh()->saldo);
        $this->assertSame('3000.00', $this->cliente->fresh()->cuentaCorriente->saldoAFavor());

        DB::transaction(fn () => $this->cuentas->debitar($this->cliente, '3000.00', 'Uso del resto'));
        $this->assertSame('0.00', $this->cliente->fresh()->cuentaCorriente->saldo);
    }

    public function test_a_debit_that_would_create_debt_without_credit_is_rejected(): void
    {
        DB::transaction(fn () => $this->cuentas->acreditar($this->cliente, '3000.00', 'Devolución'));

        try {
            DB::transaction(fn () => $this->cuentas->debitar($this->cliente, '5000.00', 'Pago'));
            $this->fail('Se esperaba CreditoNoDisponible.');
        } catch (CreditoNoDisponible $e) {
            $this->assertSame('3000.00', $e->disponible);
            $this->assertSame('El cliente no tiene crédito habilitado: solo puede usar su saldo a favor de $ 3.000,00.', $e->getMessage());
        }

        $this->assertSame('-3000.00', $this->cliente->fresh()->cuentaCorriente->saldo);
        $this->assertSame(1, CcMovimiento::count());
    }

    public function test_a_customer_without_account_cannot_be_debited(): void
    {
        $this->expectException(CreditoNoDisponible::class);

        DB::transaction(fn () => $this->cuentas->debitar($this->cliente, '100.00', 'Pago'));
    }

    public function test_credit_accounts_respect_the_limit_and_the_block(): void
    {
        $cuenta = CuentaCorriente::factory()->conCredito('10000.00')->create(['cliente_id' => $this->cliente->id]);

        DB::transaction(fn () => $this->cuentas->debitar($this->cliente, '6000.00', 'Venta a cuenta', vencimiento: now()->addMonth()));
        $this->assertSame('6000.00', $cuenta->fresh()->deuda());
        $this->assertNotNull(CcMovimiento::sole()->vencimiento);

        try {
            DB::transaction(fn () => $this->cuentas->debitar($this->cliente, '4000.01', 'Venta a cuenta'));
            $this->fail('Se esperaba CreditoNoDisponible por límite.');
        } catch (CreditoNoDisponible $e) {
            $this->assertSame('4000.00', $e->disponible);
        }

        $cuenta->forceFill(['bloqueada' => true])->save();

        $this->expectException(CreditoNoDisponible::class);
        DB::transaction(fn () => $this->cuentas->debitar($this->cliente, '1.00', 'Venta a cuenta'));
    }

    public function test_a_blocked_account_can_still_use_its_credit_balance(): void
    {
        CuentaCorriente::factory()->conCredito()->bloqueada()->create(['cliente_id' => $this->cliente->id]);
        DB::transaction(fn () => $this->cuentas->acreditar($this->cliente, '500.00', 'Devolución'));

        DB::transaction(fn () => $this->cuentas->debitar($this->cliente, '500.00', 'Pago con saldo a favor'));

        $this->assertSame('0.00', $this->cliente->fresh()->cuentaCorriente->saldo);
    }

    public function test_invalid_amounts_and_empty_concepts_are_rejected(): void
    {
        foreach (['0', '-10.00', '10.001', 'abc'] as $importe) {
            try {
                DB::transaction(fn () => $this->cuentas->acreditar($this->cliente, $importe, 'Concepto'));
                $this->fail("Se esperaba InvalidArgumentException para {$importe}.");
            } catch (InvalidArgumentException) {
                // expected
            }
        }

        $this->expectException(InvalidArgumentException::class);
        DB::transaction(fn () => $this->cuentas->acreditar($this->cliente, '10.00', '   '));
    }

    public function test_reversing_an_unused_credit_returns_the_balance_to_zero_and_is_audited(): void
    {
        $original = DB::transaction(fn () => $this->cuentas->acreditar($this->cliente, '8000.00', 'Devolución'));

        $contraasiento = DB::transaction(fn () => $this->cuentas->contraasentar($original, 'Devolución cargada dos veces'));

        $this->assertSame(TipoMovimientoCuenta::Debe, $contraasiento->tipo);
        $this->assertSame('8000.00', $contraasiento->fresh()->importe);
        $this->assertSame($original->id, $contraasiento->reversa_de_id);
        $this->assertTrue($contraasiento->esContraasiento());
        $this->assertSame('Devolución cargada dos veces', $contraasiento->concepto);
        $this->assertSame('0.00', $this->cliente->fresh()->cuentaCorriente->saldo);
        $this->assertTrue($original->fresh()->contraasiento->is($contraasiento));

        $auditoria = Auditoria::where('accion', 'cuenta_corriente.contraasiento')->sole();
        $this->assertSame($original->id, $auditoria->antes['movimiento_id']);
        $this->assertSame('debe', $auditoria->despues['tipo']);
        $this->assertSame('Devolución cargada dos veces', $auditoria->despues['motivo']);
    }

    public function test_a_movement_is_reversed_only_once_and_a_reversal_cannot_be_reversed(): void
    {
        $original = DB::transaction(fn () => $this->cuentas->acreditar($this->cliente, '100.00', 'Devolución'));
        $contraasiento = DB::transaction(fn () => $this->cuentas->contraasentar($original, 'Error'));

        foreach ([$original, $contraasiento] as $movimiento) {
            try {
                DB::transaction(fn () => $this->cuentas->contraasentar($movimiento, 'Otra vez'));
                $this->fail('Se esperaba InvalidArgumentException.');
            } catch (InvalidArgumentException) {
                // expected
            }
        }

        $this->assertSame(2, CcMovimiento::count());

        $this->expectException(InvalidArgumentException::class);
        $otro = DB::transaction(fn () => $this->cuentas->acreditar($this->cliente, '100.00', 'Devolución'));
        DB::transaction(fn () => $this->cuentas->contraasentar($otro, ' '));
    }

    public function test_reversing_a_credit_already_used_without_credit_is_rejected(): void
    {
        $haber = DB::transaction(fn () => $this->cuentas->acreditar($this->cliente, '8000.00', 'Devolución'));
        DB::transaction(fn () => $this->cuentas->debitar($this->cliente, '5000.00', 'Pago con saldo a favor'));

        $this->expectException(CreditoNoDisponible::class);

        try {
            DB::transaction(fn () => $this->cuentas->contraasentar($haber, 'Devolución anulada'));
        } finally {
            $this->assertSame('-3000.00', $this->cliente->fresh()->cuentaCorriente->saldo);
            $this->assertSame(0, Auditoria::where('accion', 'cuenta_corriente.contraasiento')->count());
        }
    }

    public function test_reversing_a_debit_restores_the_credit_balance(): void
    {
        DB::transaction(fn () => $this->cuentas->acreditar($this->cliente, '8000.00', 'Devolución'));
        $debe = DB::transaction(fn () => $this->cuentas->debitar($this->cliente, '5000.00', 'Pago con saldo a favor'));

        $contraasiento = DB::transaction(fn () => $this->cuentas->contraasentar($debe, 'Venta anulada'));

        $this->assertSame(TipoMovimientoCuenta::Haber, $contraasiento->tipo);
        $this->assertSame('-8000.00', $this->cliente->fresh()->cuentaCorriente->saldo);
    }

    public function test_movements_are_immutable(): void
    {
        $movimiento = DB::transaction(fn () => $this->cuentas->acreditar($this->cliente, '100.00', 'Devolución'));

        try {
            $movimiento->update(['concepto' => 'Otro']);
            $this->fail('Se esperaba LogicException al modificar.');
        } catch (LogicException) {
            // expected
        }

        try {
            $movimiento->delete();
            $this->fail('Se esperaba LogicException al borrar.');
        } catch (LogicException) {
            // expected
        }

        $this->assertSame('Devolución', $movimiento->fresh()->concepto);
    }

    public function test_the_service_rejects_calls_outside_a_transaction(): void
    {
        DB::partialMock()->shouldReceive('transactionLevel')->andReturn(0);

        foreach ([
            fn () => $this->cuentas->cuentaDe($this->cliente),
            fn () => $this->cuentas->acreditar($this->cliente, '10.00', 'Fuera'),
            fn () => $this->cuentas->debitar($this->cliente, '10.00', 'Fuera'),
        ] as $llamada) {
            try {
                $llamada();
                $this->fail('Se esperaba LogicException.');
            } catch (LogicException) {
                // expected
            }
        }

        $this->assertSame(0, CuentaCorriente::count());
        $this->assertSame(0, CcMovimiento::count());
    }

    public function test_a_branch_is_required(): void
    {
        app(SucursalActiva::class)->olvidar();

        $this->expectException(LogicException::class);

        DB::transaction(fn () => $this->cuentas->acreditar($this->cliente, '10.00', 'Sin sucursal'));
    }

    public function test_an_explicit_branch_and_business_date_are_kept(): void
    {
        $otra = Sucursal::factory()->create();

        $movimiento = DB::transaction(fn () => $this->cuentas->acreditar(
            $this->cliente, '10.00', 'Devolución', fecha: now()->subDay(), sucursal: $otra,
        ));

        $this->assertSame($otra->id, $movimiento->sucursal_id);
        $this->assertSame(now()->subDay()->toDateString(), $movimiento->fresh()->fecha->toDateString());
    }

    public function test_balance_from_movements_matches_the_cached_balance(): void
    {
        DB::transaction(function () {
            $this->cuentas->acreditar($this->cliente, '5000.00', 'Devolución');
            $this->cuentas->debitar($this->cliente, '2000.00', 'Pago');
        });

        $cuenta = $this->cliente->fresh()->cuentaCorriente;
        $this->assertSame('-3000.00', $this->cuentas->saldoSegunMovimientos($cuenta));
        $this->assertSame('-3000.00', $cuenta->saldo);
    }
}
