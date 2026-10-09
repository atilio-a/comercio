<?php

namespace Tests\Feature\Caja;

use App\Enums\EstadoSesionCaja;
use App\Enums\TipoMedioPago;
use App\Enums\TipoMovimientoCaja;
use App\Exceptions\CajaYaAbierta;
use App\Models\Auditoria;
use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Models\MedioPago;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\MovimientosCaja;
use App\Services\SesionesCaja;
use App\Services\SucursalActiva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class SesionesCajaTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $user;

    private Caja $caja;

    private MedioPago $efectivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::factory()->create();
        $this->user = User::factory()->conSucursal($this->sucursal)->create();
        $this->caja = Caja::factory()->create(['sucursal_id' => $this->sucursal->id]);
        $this->efectivo = MedioPago::factory()->efectivo()->create();

        $this->actingAs($this->user);
        app(SucursalActiva::class)->establecer($this->sucursal);
    }

    public function test_opening_with_a_float_registers_an_opening_movement_in_cash(): void
    {
        $sesion = $this->sesiones()->abrir($this->caja, '5000');

        $this->assertSame(EstadoSesionCaja::Abierta, $sesion->estado);
        $this->assertSame($this->user->id, $sesion->user_id);
        $this->assertSame($this->sucursal->id, $sesion->sucursal_id);
        $this->assertSame('5000.00', $sesion->fresh()->fondo_inicial);

        $apertura = CajaMovimiento::sole();
        $this->assertSame(TipoMovimientoCaja::Apertura, $apertura->tipo);
        $this->assertSame('5000.00', $apertura->importe);
        $this->assertSame($this->efectivo->id, $apertura->medio_pago_id);
        $this->assertTrue(Auditoria::where('accion', 'caja.apertura')->where('modelo_id', $sesion->id)->exists());
    }

    public function test_opening_without_a_float_registers_no_movement(): void
    {
        $this->sesiones()->abrir($this->caja, '0');

        $this->assertSame(0, CajaMovimiento::count());
        $this->assertSame(1, CajaSesion::count());
    }

    public function test_a_cash_register_has_only_one_open_session(): void
    {
        $this->sesiones()->abrir($this->caja, '0');

        $otro = User::factory()->conSucursal($this->sucursal)->create();
        $this->actingAs($otro);

        $this->assertThrows(fn () => $this->sesiones()->abrir($this->caja, '100'), CajaYaAbierta::class);
        $this->assertSame(1, CajaSesion::where('caja_id', $this->caja->id)->where('estado', 'abierta')->count());
        $this->assertSame(0, CajaMovimiento::count());
    }

    public function test_a_user_cannot_open_a_second_cash_register_in_the_branch(): void
    {
        $this->sesiones()->abrir($this->caja, '0');
        $otraCaja = Caja::factory()->create(['sucursal_id' => $this->sucursal->id]);

        $this->expectException(CajaYaAbierta::class);

        $this->sesiones()->abrir($otraCaja, '0');
    }

    public function test_inactive_or_foreign_cash_registers_cannot_be_opened(): void
    {
        $inactiva = Caja::factory()->inactiva()->create(['sucursal_id' => $this->sucursal->id]);
        $ajena = Caja::factory()->create();

        $this->assertThrows(fn () => $this->sesiones()->abrir($inactiva, '0'), InvalidArgumentException::class);
        $this->assertThrows(fn () => $this->sesiones()->abrir($ajena, '0'), InvalidArgumentException::class);
        $this->assertThrows(fn () => $this->sesiones()->abrir($this->caja, '-1'), InvalidArgumentException::class);
        $this->assertSame(0, CajaSesion::count());
    }

    public function test_expected_balance_per_payment_method(): void
    {
        $sesion = $this->sesionConMovimientos();

        $this->assertSame([$this->efectivo->id => '4099.50'], $sesion->esperadoPorMedio());
    }

    public function test_closing_without_differences(): void
    {
        $sesion = $this->sesionConMovimientos();

        $this->sesiones()->cerrar($sesion, [$this->efectivo->id => '4099.50'], null);

        $sesion->refresh();
        $this->assertSame(EstadoSesionCaja::Cerrada, $sesion->estado);
        $this->assertNotNull($sesion->cierre);
        $arqueo = $sesion->arqueos()->sole();
        $this->assertSame(['4099.50', '4099.50', '0.00'], [$arqueo->esperado, $arqueo->contado, $arqueo->diferencia]);
        $this->assertTrue(Auditoria::where('accion', 'caja.cierre')->exists());
        $this->assertFalse(Auditoria::where('accion', 'caja.diferencia')->exists());
    }

    public function test_a_difference_without_justification_is_rejected_and_the_session_stays_open(): void
    {
        $sesion = $this->sesionConMovimientos();

        $this->assertThrows(fn () => $this->sesiones()->cerrar($sesion, [$this->efectivo->id => '4000'], '  '), InvalidArgumentException::class);

        $this->assertSame(EstadoSesionCaja::Abierta, $sesion->fresh()->estado);
        $this->assertSame(0, $sesion->arqueos()->count());
    }

    public function test_a_justified_difference_closes_the_session_and_is_audited(): void
    {
        $sesion = $this->sesionConMovimientos();

        $this->sesiones()->cerrar($sesion, [$this->efectivo->id => '4000'], 'Faltante de cambio');

        $sesion->refresh();
        $this->assertSame(EstadoSesionCaja::Cerrada, $sesion->estado);
        $this->assertSame('Faltante de cambio', $sesion->justificacion);
        $this->assertSame('-99.50', $sesion->arqueos()->sole()->diferencia);

        $auditoria = Auditoria::where('accion', 'caja.diferencia')->sole();
        $this->assertSame([(string) $this->efectivo->id => '-99.50'], $auditoria->despues['diferencias']);
        $this->assertSame('Faltante de cambio', $auditoria->despues['justificacion']);
    }

    public function test_counted_amounts_are_required_for_cash_methods_and_methods_not_counted_keep_their_expected_total(): void
    {
        $sesion = $this->sesionConMovimientos();
        $debito = MedioPago::factory()->tipo(TipoMedioPago::TarjetaDebito)->create();
        DB::transaction(fn () => app(MovimientosCaja::class)->registrar(TipoMovimientoCaja::Venta, $debito, '2500.00'));

        $this->assertThrows(fn () => $this->sesiones()->cerrar($sesion, [], null), InvalidArgumentException::class);

        $this->sesiones()->cerrar($sesion, [$this->efectivo->id => '4099.50'], null);

        $arqueoDebito = $sesion->arqueos()->where('medio_pago_id', $debito->id)->sole();
        $this->assertSame('2500.00', $arqueoDebito->esperado);
        $this->assertNull($arqueoDebito->contado);
        $this->assertNull($arqueoDebito->diferencia);
    }

    public function test_a_closed_session_cannot_be_closed_again_nor_receive_movements(): void
    {
        $sesion = $this->sesionConMovimientos();
        $this->sesiones()->cerrar($sesion, [$this->efectivo->id => '4099.50'], null);

        $this->assertThrows(fn () => $this->sesiones()->cerrar($sesion, [$this->efectivo->id => '1'], 'x'));
        $this->assertThrows(fn () => DB::transaction(fn () => app(MovimientosCaja::class)->registrar(TipoMovimientoCaja::Ingreso, $this->efectivo, '1.00', 'x')));
    }

    public function test_only_the_owner_closes_the_session(): void
    {
        $sesion = $this->sesionConMovimientos();
        $this->actingAs(User::factory()->conSucursal($this->sucursal)->create());

        $this->assertThrows(fn () => $this->sesiones()->cerrar($sesion, [$this->efectivo->id => '4099.50'], null), InvalidArgumentException::class);
        $this->assertSame(EstadoSesionCaja::Abierta, $sesion->fresh()->estado);
    }

    /**
     * Apertura de 5.000,00, ingreso de 300,00 y egreso de 1.200,50: esperado 4.099,50.
     */
    private function sesionConMovimientos(): CajaSesion
    {
        $sesion = $this->sesiones()->abrir($this->caja, '5000.00');

        DB::transaction(function () {
            app(MovimientosCaja::class)->registrar(TipoMovimientoCaja::Ingreso, $this->efectivo, '300.00', 'Cambio');
            app(MovimientosCaja::class)->registrar(TipoMovimientoCaja::Egreso, $this->efectivo, '-1200.50', 'Compra de bolsas');
        });

        return $sesion;
    }

    private function sesiones(): SesionesCaja
    {
        return app(SesionesCaja::class);
    }
}
