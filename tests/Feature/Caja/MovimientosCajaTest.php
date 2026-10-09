<?php

namespace Tests\Feature\Caja;

use App\Enums\TipoMedioPago;
use App\Enums\TipoMovimientoCaja;
use App\Exceptions\CajaNoAbierta;
use App\Http\Middleware\IdentificarDispositivo;
use App\Models\Auditoria;
use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Models\MedioPago;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\MovimientosCaja;
use App\Services\SucursalActiva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class MovimientosCajaTest extends TestCase
{
    use RefreshDatabase;

    private const DISPOSITIVO = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';

    private Sucursal $sucursal;

    private User $user;

    private CajaSesion $sesion;

    private MedioPago $efectivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::factory()->create();
        $this->user = User::factory()->conSucursal($this->sucursal)->create();
        $caja = Caja::factory()->create(['sucursal_id' => $this->sucursal->id]);
        $this->sesion = CajaSesion::factory()->for($caja)->for($this->user)->create();
        $this->efectivo = MedioPago::factory()->efectivo()->create();

        $this->actingAs($this->user);
        app(SucursalActiva::class)->establecer($this->sucursal);
        request()->attributes->set(IdentificarDispositivo::ATRIBUTO, self::DISPOSITIVO);
    }

    public function test_a_movement_goes_to_the_users_open_session_with_user_device_and_date(): void
    {
        $movimiento = $this->registrar(TipoMovimientoCaja::Egreso, '-1200.5', 'Compra de bolsas');

        $this->assertSame($this->sesion->id, $movimiento->caja_sesion_id);
        $this->assertSame($this->sucursal->id, $movimiento->sucursal_id);
        $this->assertSame('-1200.50', $movimiento->fresh()->importe);
        $this->assertSame(TipoMovimientoCaja::Egreso, $movimiento->tipo);
        $this->assertSame('Compra de bolsas', $movimiento->motivo);
        $this->assertSame($this->user->id, $movimiento->user_id);
        $this->assertSame(self::DISPOSITIVO, $movimiento->dispositivo);
        $this->assertSame(now()->toDateString(), $movimiento->fresh()->fecha->toDateString());
    }

    public function test_the_service_rejects_calls_outside_a_transaction(): void
    {
        DB::partialMock()->shouldReceive('transactionLevel')->andReturn(0);

        try {
            app(MovimientosCaja::class)->registrar(TipoMovimientoCaja::Ingreso, $this->efectivo, '10.00', 'Fuera');
            $this->fail('Se esperaba LogicException.');
        } catch (LogicException) {
            $this->assertSame(0, CajaMovimiento::count());
        }
    }

    public function test_the_sign_must_match_the_type(): void
    {
        $this->assertThrows(fn () => $this->registrar(TipoMovimientoCaja::Egreso, '100.00', 'Retiro'), InvalidArgumentException::class);
        $this->assertThrows(fn () => $this->registrar(TipoMovimientoCaja::Venta, '-100.00'), InvalidArgumentException::class);
        $this->assertSame(0, CajaMovimiento::count());
    }

    public function test_zero_and_invalid_amounts_are_rejected(): void
    {
        foreach (['0', '0.00', '10.005', 'abc', '1e3'] as $importe) {
            $this->assertThrows(fn () => $this->registrar(TipoMovimientoCaja::Ingreso, $importe, 'Prueba'), InvalidArgumentException::class);
        }

        $this->assertSame(0, CajaMovimiento::count());
    }

    public function test_income_and_expense_require_a_reason(): void
    {
        $this->assertThrows(fn () => $this->registrar(TipoMovimientoCaja::Egreso, '-10.00', '   '), InvalidArgumentException::class);
        $this->assertThrows(fn () => $this->registrar(TipoMovimientoCaja::Ingreso, '10.00', null), InvalidArgumentException::class);

        $venta = $this->registrar(TipoMovimientoCaja::Venta, '10.00');

        $this->assertNull($venta->motivo);
        $this->assertSame(1, CajaMovimiento::count());
    }

    public function test_a_payment_method_that_requires_a_reference_needs_one(): void
    {
        $transferencia = MedioPago::factory()->tipo(TipoMedioPago::Transferencia)->create(['requiere_referencia' => true]);

        $this->assertThrows(
            fn () => DB::transaction(fn () => app(MovimientosCaja::class)->registrar(TipoMovimientoCaja::Ingreso, $transferencia, '50.00', 'Seña')),
            InvalidArgumentException::class,
        );

        $movimiento = DB::transaction(fn () => app(MovimientosCaja::class)->registrar(
            TipoMovimientoCaja::Ingreso, $transferencia, '50.00', 'Seña', referencia: 'OP-123',
        ));

        $this->assertSame('OP-123', $movimiento->referencia);
    }

    public function test_an_inactive_payment_method_is_rejected(): void
    {
        $inactivo = MedioPago::factory()->efectivo()->inactivo()->create();

        $this->expectException(InvalidArgumentException::class);

        DB::transaction(fn () => app(MovimientosCaja::class)->registrar(TipoMovimientoCaja::Ingreso, $inactivo, '10.00', 'Prueba'));
    }

    public function test_without_an_open_session_the_movement_is_rejected(): void
    {
        $otro = User::factory()->conSucursal($this->sucursal)->create();
        $this->actingAs($otro);

        $this->expectException(CajaNoAbierta::class);

        $this->registrar(TipoMovimientoCaja::Ingreso, '10.00', 'Prueba');
    }

    public function test_the_open_session_is_looked_up_in_the_active_branch(): void
    {
        $otraSucursal = Sucursal::factory()->create();
        app(SucursalActiva::class)->establecer($otraSucursal);

        $this->expectException(CajaNoAbierta::class);

        $this->registrar(TipoMovimientoCaja::Ingreso, '10.00', 'Prueba');
    }

    public function test_a_closed_session_rejects_movements(): void
    {
        $cerrada = CajaSesion::factory()->cerrada()->create();

        $this->expectException(CajaNoAbierta::class);

        DB::transaction(fn () => app(MovimientosCaja::class)->registrar(
            TipoMovimientoCaja::Ingreso, $this->efectivo, '10.00', 'Prueba', sesion: $cerrada,
        ));
    }

    public function test_an_explicit_open_session_and_an_origin_can_be_given(): void
    {
        $otraSesion = CajaSesion::factory()->create();
        $debito = MedioPago::factory()->tipo(TipoMedioPago::TarjetaDebito)->create();
        $origen = User::factory()->create();

        $movimiento = DB::transaction(fn () => app(MovimientosCaja::class)->registrar(
            TipoMovimientoCaja::Venta, $debito, '2500.00', origen: $origen, sesion: $otraSesion, fecha: now()->subDay(),
        ));

        $this->assertSame($otraSesion->id, $movimiento->caja_sesion_id);
        $this->assertSame($otraSesion->sucursal_id, $movimiento->sucursal_id);
        $this->assertSame(User::class, $movimiento->origen_type);
        $this->assertSame($origen->id, $movimiento->origen_id);
        $this->assertSame(now()->subDay()->toDateString(), $movimiento->fresh()->fecha->toDateString());
        $this->assertSame([$debito->id => '2500.00'], $otraSesion->esperadoPorMedio());
    }

    public function test_a_reversal_registers_the_opposite_amount_and_is_audited(): void
    {
        $this->registrar(TipoMovimientoCaja::Ingreso, '300.00', 'Cambio');
        $egreso = $this->registrar(TipoMovimientoCaja::Egreso, '-1000.00', 'Retiro');

        $contraasiento = DB::transaction(fn () => app(MovimientosCaja::class)->contraasentar($egreso, 'Cargado dos veces'));

        $this->assertSame(TipoMovimientoCaja::Egreso, $contraasiento->tipo);
        $this->assertSame('1000.00', $contraasiento->fresh()->importe);
        $this->assertSame($egreso->id, $contraasiento->reversa_de_id);
        $this->assertSame($this->efectivo->id, $contraasiento->medio_pago_id);
        $this->assertSame([$this->efectivo->id => '300.00'], $this->sesion->esperadoPorMedio());

        $auditoria = Auditoria::where('accion', 'caja.contraasiento')->sole();
        $this->assertSame($contraasiento->id, $auditoria->modelo_id);
        $this->assertSame('Cargado dos veces', $auditoria->despues['motivo']);
        $this->assertSame($egreso->id, $auditoria->antes['movimiento_id']);
    }

    public function test_a_movement_is_reversed_only_once_and_a_reversal_is_never_reversed(): void
    {
        $egreso = $this->registrar(TipoMovimientoCaja::Egreso, '-1000.00', 'Retiro');
        $contraasiento = DB::transaction(fn () => app(MovimientosCaja::class)->contraasentar($egreso, 'Error'));

        $this->assertThrows(fn () => DB::transaction(fn () => app(MovimientosCaja::class)->contraasentar($egreso, 'Otra vez')), InvalidArgumentException::class);
        $this->assertThrows(fn () => DB::transaction(fn () => app(MovimientosCaja::class)->contraasentar($contraasiento, 'Revertir')), InvalidArgumentException::class);
        $this->assertSame(2, CajaMovimiento::count());
    }

    public function test_a_reversal_requires_a_reason_and_an_open_session(): void
    {
        $egreso = $this->registrar(TipoMovimientoCaja::Egreso, '-1000.00', 'Retiro');

        $this->assertThrows(fn () => DB::transaction(fn () => app(MovimientosCaja::class)->contraasentar($egreso, ' ')), InvalidArgumentException::class);

        $this->sesion->forceFill(['estado' => 'cerrada', 'cierre' => now()])->save();

        $this->assertThrows(fn () => DB::transaction(fn () => app(MovimientosCaja::class)->contraasentar($egreso->fresh(), 'Error')), CajaNoAbierta::class);
        $this->assertSame(1, CajaMovimiento::count());
    }

    private function registrar(TipoMovimientoCaja $tipo, string $importe, ?string $motivo = null): CajaMovimiento
    {
        return DB::transaction(fn () => app(MovimientosCaja::class)->registrar($tipo, $this->efectivo, $importe, $motivo));
    }
}
