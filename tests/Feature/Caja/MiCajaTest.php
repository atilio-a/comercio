<?php

namespace Tests\Feature\Caja;

use App\Enums\EstadoSesionCaja;
use App\Enums\Rol;
use App\Enums\TipoMedioPago;
use App\Enums\TipoMovimientoCaja;
use App\Models\Auditoria;
use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Models\MedioPago;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiCajaTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private Caja $caja;

    private MedioPago $efectivo;

    private User $cajero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->sucursal = Sucursal::factory()->create(['nombre' => Sucursal::PRINCIPAL]);
        $this->caja = Caja::factory()->create(['sucursal_id' => $this->sucursal->id, 'nombre' => Caja::PRINCIPAL]);
        $this->efectivo = MedioPago::factory()->efectivo()->create(['nombre' => 'Efectivo']);
        $this->cajero = User::factory()->conSucursal($this->sucursal, Rol::Cajero)->create();
    }

    public function test_cashier_opens_the_cash_register_with_a_float(): void
    {
        $this->actingAs($this->cajero)->get(route('caja.mi-caja'))
            ->assertOk()
            ->assertSee('Abrir caja')
            ->assertSee(Caja::PRINCIPAL);

        $this->actingAs($this->cajero)->post(route('caja.abrir'), [
            'caja_id' => $this->caja->id,
            'fondo_inicial' => '5000',
        ])->assertRedirect(route('caja.mi-caja'))->assertSessionHasNoErrors();

        $sesion = CajaSesion::sole();
        $this->assertSame($this->cajero->id, $sesion->user_id);
        $this->assertSame(EstadoSesionCaja::Abierta, $sesion->estado);
        $this->assertNotNull($sesion->dispositivo);
        $this->assertSame('5000.00', CajaMovimiento::sole()->importe);

        $this->actingAs($this->cajero)->get(route('caja.mi-caja'))
            ->assertOk()
            ->assertSee('Saldo esperado')
            ->assertSee('5.000,00')
            ->assertSee('Cerrar caja');
    }

    public function test_a_cash_register_already_open_by_someone_else_is_rejected(): void
    {
        $this->abrirCaja($this->cajero);
        $vendedor = User::factory()->conSucursal($this->sucursal, Rol::Vendedor)->create();

        $this->actingAs($vendedor)->post(route('caja.abrir'), [
            'caja_id' => $this->caja->id,
            'fondo_inicial' => '0',
        ])->assertSessionHasErrors('caja_id');

        $this->assertSame(1, CajaSesion::count());
    }

    public function test_a_cash_register_of_another_branch_cannot_be_opened(): void
    {
        $ajena = Caja::factory()->create();

        $this->actingAs($this->cajero)->post(route('caja.abrir'), [
            'caja_id' => $ajena->id,
            'fondo_inicial' => '0',
        ])->assertSessionHasErrors('caja_id');

        $this->assertSame(0, CajaSesion::count());
    }

    public function test_storage_role_cannot_operate_the_cash_register(): void
    {
        $deposito = User::factory()->conSucursal($this->sucursal, Rol::Deposito)->create();

        $this->actingAs($deposito)->get(route('caja.mi-caja'))->assertForbidden();
        $this->actingAs($deposito)->post(route('caja.abrir'), [
            'caja_id' => $this->caja->id,
            'fondo_inicial' => '0',
        ])->assertForbidden();

        $this->assertSame(0, CajaSesion::count());
    }

    public function test_expense_with_reason_is_registered_with_negative_amount(): void
    {
        $this->abrirCaja($this->cajero, '5000');

        $this->actingAs($this->cajero)->post(route('caja.movimientos.store'), [
            'tipo' => 'egreso',
            'medio_pago_id' => $this->efectivo->id,
            'importe' => '1200.50',
            'motivo' => 'Compra de bolsas',
        ])->assertRedirect(route('caja.mi-caja'))->assertSessionHasNoErrors();

        $egreso = CajaMovimiento::where('tipo', TipoMovimientoCaja::Egreso->value)->sole();
        $this->assertSame('-1200.50', $egreso->importe);
        $this->assertSame('Compra de bolsas', $egreso->motivo);
        $this->assertSame($this->cajero->id, $egreso->user_id);

        $this->actingAs($this->cajero)->get(route('caja.mi-caja'))
            ->assertOk()
            ->assertSee('Compra de bolsas')
            ->assertSee('3.799,50');
    }

    public function test_expense_without_reason_is_rejected(): void
    {
        $this->abrirCaja($this->cajero);

        $this->actingAs($this->cajero)->post(route('caja.movimientos.store'), [
            'tipo' => 'egreso',
            'medio_pago_id' => $this->efectivo->id,
            'importe' => '100',
            'motivo' => '',
        ])->assertSessionHasErrors('motivo');

        $this->assertSame(0, CajaMovimiento::count());
    }

    public function test_invalid_amounts_types_and_payment_methods_are_rejected(): void
    {
        $this->abrirCaja($this->cajero);
        $cuentaCorriente = MedioPago::factory()->tipo(TipoMedioPago::CuentaCorriente)->create();
        $inactivo = MedioPago::factory()->efectivo()->inactivo()->create();

        $base = ['tipo' => 'ingreso', 'medio_pago_id' => $this->efectivo->id, 'importe' => '10', 'motivo' => 'Prueba'];

        $this->actingAs($this->cajero)->post(route('caja.movimientos.store'), ['importe' => '0'] + $base)->assertSessionHasErrors('importe');
        $this->actingAs($this->cajero)->post(route('caja.movimientos.store'), ['importe' => '-5'] + $base)->assertSessionHasErrors('importe');
        $this->actingAs($this->cajero)->post(route('caja.movimientos.store'), ['importe' => '1.005'] + $base)->assertSessionHasErrors('importe');
        $this->actingAs($this->cajero)->post(route('caja.movimientos.store'), ['tipo' => 'venta'] + $base)->assertSessionHasErrors('tipo');
        $this->actingAs($this->cajero)->post(route('caja.movimientos.store'), ['medio_pago_id' => $cuentaCorriente->id] + $base)->assertSessionHasErrors('medio_pago_id');
        $this->actingAs($this->cajero)->post(route('caja.movimientos.store'), ['medio_pago_id' => $inactivo->id] + $base)->assertSessionHasErrors('medio_pago_id');

        $this->assertSame(0, CajaMovimiento::count());
    }

    public function test_reference_is_required_when_the_payment_method_asks_for_it(): void
    {
        $this->abrirCaja($this->cajero);
        $transferencia = MedioPago::factory()->create(['requiere_referencia' => true]);

        $this->actingAs($this->cajero)->post(route('caja.movimientos.store'), [
            'tipo' => 'ingreso',
            'medio_pago_id' => $transferencia->id,
            'importe' => '10',
            'motivo' => 'Seña',
        ])->assertSessionHasErrors('referencia');

        $this->assertSame(0, CajaMovimiento::count());
    }

    public function test_without_an_open_session_movements_are_rejected(): void
    {
        $this->actingAs($this->cajero)->post(route('caja.movimientos.store'), [
            'tipo' => 'ingreso',
            'medio_pago_id' => $this->efectivo->id,
            'importe' => '10',
            'motivo' => 'Prueba',
        ])->assertSessionHasErrors('importe');

        $this->assertSame(0, CajaMovimiento::count());
    }

    public function test_closing_without_differences(): void
    {
        $sesion = $this->abrirCaja($this->cajero, '1000');

        $this->actingAs($this->cajero)->get(route('caja.cierre.edit'))->assertOk()->assertSee('1.000,00');

        $this->actingAs($this->cajero)->put(route('caja.cierre.update'), [
            'contado' => [$this->efectivo->id => '1000'],
        ])->assertRedirect(route('caja.sesiones.show', $sesion))->assertSessionHasNoErrors();

        $this->assertSame(EstadoSesionCaja::Cerrada, $sesion->fresh()->estado);
        $this->assertSame('0.00', $sesion->arqueos()->sole()->diferencia);

        $this->actingAs($this->cajero)->get(route('caja.sesiones.show', $sesion))->assertOk()->assertSee('Arqueo');
        $this->actingAs($this->cajero)->get(route('caja.mi-caja'))->assertOk()->assertSee('Abrir caja')->assertSee('Mis últimos cierres');
    }

    public function test_closing_with_a_difference_requires_a_justification(): void
    {
        $sesion = $this->abrirCaja($this->cajero, '1000');

        $this->actingAs($this->cajero)->put(route('caja.cierre.update'), [
            'contado' => [$this->efectivo->id => '900.50'],
            'justificacion' => '',
        ])->assertSessionHasErrors('justificacion');

        $this->assertSame(EstadoSesionCaja::Abierta, $sesion->fresh()->estado);

        $this->actingAs($this->cajero)->put(route('caja.cierre.update'), [
            'contado' => [$this->efectivo->id => '900.50'],
            'justificacion' => 'Faltante de cambio',
        ])->assertSessionHasNoErrors();

        $sesion->refresh();
        $this->assertSame(EstadoSesionCaja::Cerrada, $sesion->estado);
        $this->assertSame('-99.50', $sesion->arqueos()->sole()->diferencia);
        $this->assertTrue(Auditoria::where('accion', 'caja.diferencia')->where('modelo_id', $sesion->id)->exists());
    }

    public function test_counted_amount_is_required_for_cash(): void
    {
        $sesion = $this->abrirCaja($this->cajero, '1000');

        $this->actingAs($this->cajero)->put(route('caja.cierre.update'), ['contado' => []])
            ->assertSessionHasErrors('contado.'.$this->efectivo->id);

        $this->assertSame(EstadoSesionCaja::Abierta, $sesion->fresh()->estado);
    }

    public function test_cash_screen_is_isolated_by_branch(): void
    {
        $norte = Sucursal::factory()->create();
        $cajaNorte = Caja::factory()->create(['sucursal_id' => $norte->id, 'nombre' => 'Caja Norte']);
        $otro = User::factory()->conSucursal($norte, Rol::Cajero)->create();
        $this->abrirCaja($otro, '0', $cajaNorte);
        $this->flushSession();

        $this->actingAs($this->cajero)->get(route('caja.mi-caja'))
            ->assertOk()
            ->assertSee(Caja::PRINCIPAL)
            ->assertDontSee('Caja Norte');

        $this->actingAs($this->cajero)->get(route('caja.cierre.edit'))->assertRedirect(route('caja.mi-caja'));
    }

    private function abrirCaja(User $user, string $fondo = '0', ?Caja $caja = null): CajaSesion
    {
        $this->actingAs($user)->post(route('caja.abrir'), [
            'caja_id' => ($caja ?? $this->caja)->id,
            'fondo_inicial' => $fondo,
        ])->assertSessionHasNoErrors();

        return CajaSesion::where('user_id', $user->id)->where('estado', 'abierta')->sole();
    }
}
