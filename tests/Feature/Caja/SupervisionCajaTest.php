<?php

namespace Tests\Feature\Caja;

use App\Enums\Rol;
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

class SupervisionCajaTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $cajero;

    private User $gerente;

    private CajaSesion $sesion;

    private CajaMovimiento $egreso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->sucursal = Sucursal::factory()->create(['nombre' => Sucursal::PRINCIPAL]);
        $this->cajero = User::factory()->conSucursal($this->sucursal, Rol::Cajero)->create(['name' => 'Ana Cajera']);
        $this->gerente = User::factory()->conSucursal($this->sucursal, Rol::Gerente)->create();

        $caja = Caja::factory()->create(['sucursal_id' => $this->sucursal->id, 'nombre' => Caja::PRINCIPAL]);
        $this->sesion = CajaSesion::factory()->for($caja)->for($this->cajero)->create();
        $this->egreso = CajaMovimiento::factory()->for($this->sesion, 'sesion')->create([
            'tipo' => TipoMovimientoCaja::Egreso,
            'importe' => '-1000.00',
            'motivo' => 'Retiro',
            'medio_pago_id' => MedioPago::factory()->efectivo()->create()->id,
        ]);
    }

    public function test_manager_lists_and_views_sessions_of_the_branch(): void
    {
        $ajena = CajaSesion::factory()->create();
        $ajena->caja->update(['nombre' => 'Caja de otra sucursal']);

        $this->actingAs($this->gerente)->get(route('caja.sesiones.index'))
            ->assertOk()
            ->assertSee('Ana Cajera')
            ->assertDontSee('Caja de otra sucursal');

        $this->actingAs($this->gerente)->get(route('caja.sesiones.show', $this->sesion))
            ->assertOk()
            ->assertSee('Retiro')
            ->assertSee('Contraasentar');

        $this->actingAs($this->gerente)->get(route('caja.sesiones.show', $ajena))->assertNotFound();
    }

    public function test_filters_by_state(): void
    {
        $cerrada = CajaSesion::factory()->cerrada()->create([
            'caja_id' => Caja::factory()->create(['sucursal_id' => $this->sucursal->id, 'nombre' => 'Caja 2'])->id,
        ]);

        $this->actingAs($this->gerente)->get(route('caja.sesiones.index', ['estado' => 'cerrada']))
            ->assertOk()
            ->assertSee(route('caja.sesiones.show', $cerrada))
            ->assertDontSee(route('caja.sesiones.show', $this->sesion));
    }

    public function test_cashier_cannot_list_sessions_but_sees_their_own(): void
    {
        $otraSesion = CajaSesion::factory()->create([
            'caja_id' => Caja::factory()->create(['sucursal_id' => $this->sucursal->id])->id,
        ]);

        $this->actingAs($this->cajero)->get(route('caja.sesiones.index'))->assertForbidden();
        $this->actingAs($this->cajero)->get(route('caja.sesiones.show', $this->sesion))
            ->assertOk()
            ->assertDontSee('Contraasentar');
        $this->actingAs($this->cajero)->get(route('caja.sesiones.show', $otraSesion))->assertForbidden();
    }

    public function test_manager_reverses_a_movement(): void
    {
        $this->actingAs($this->gerente)->post(route('caja.movimientos.contraasiento', $this->egreso), [
            'motivo' => 'Cargado dos veces',
        ])->assertRedirect(route('caja.sesiones.show', $this->sesion))->assertSessionHasNoErrors();

        $contraasiento = CajaMovimiento::where('reversa_de_id', $this->egreso->id)->sole();
        $this->assertSame('1000.00', $contraasiento->importe);
        $this->assertSame($this->gerente->id, $contraasiento->user_id);
        $this->assertSame([$this->egreso->medio_pago_id => '0.00'], $this->sesion->esperadoPorMedio());
        $this->assertTrue(Auditoria::where('accion', 'caja.contraasiento')->exists());

        $this->actingAs($this->gerente)->get(route('caja.sesiones.show', $this->sesion))
            ->assertOk()
            ->assertSee('Revertido')
            ->assertSee('Contraasiento del #'.$this->egreso->id);
    }

    public function test_a_reversal_without_reason_or_twice_is_rejected(): void
    {
        $this->actingAs($this->gerente)->post(route('caja.movimientos.contraasiento', $this->egreso), ['motivo' => ''])
            ->assertSessionHasErrorsIn('contraasiento', 'motivo');

        $this->actingAs($this->gerente)->post(route('caja.movimientos.contraasiento', $this->egreso), ['motivo' => 'Error']);
        $this->actingAs($this->gerente)->post(route('caja.movimientos.contraasiento', $this->egreso), ['motivo' => 'Otra vez'])
            ->assertSessionHasErrorsIn('contraasiento', 'motivo');

        $this->assertSame(1, CajaMovimiento::whereNotNull('reversa_de_id')->count());
    }

    public function test_movements_of_a_closed_session_are_not_reversed(): void
    {
        $this->sesion->forceFill(['estado' => 'cerrada', 'cierre' => now()])->save();

        $this->actingAs($this->gerente)->post(route('caja.movimientos.contraasiento', $this->egreso), ['motivo' => 'Error'])
            ->assertSessionHasErrorsIn('contraasiento', 'motivo');

        $this->assertSame(1, CajaMovimiento::count());
    }

    public function test_cashier_cannot_reverse(): void
    {
        $this->actingAs($this->cajero)->post(route('caja.movimientos.contraasiento', $this->egreso), ['motivo' => 'Error'])
            ->assertForbidden();

        $this->assertSame(1, CajaMovimiento::count());
    }

    public function test_movements_of_another_branch_cannot_be_reversed(): void
    {
        $ajeno = CajaMovimiento::factory()->create(['tipo' => TipoMovimientoCaja::Ingreso]);

        $this->actingAs($this->gerente)->post(route('caja.movimientos.contraasiento', $ajeno), ['motivo' => 'Error'])
            ->assertNotFound();

        $this->assertSame(2, CajaMovimiento::count());
    }

    public function test_movements_generated_by_other_modules_are_not_reversed_from_the_cash_screen(): void
    {
        $venta = CajaMovimiento::factory()->for($this->sesion, 'sesion')->create([
            'tipo' => TipoMovimientoCaja::Venta,
            'importe' => '500.00',
            'motivo' => null,
        ]);

        $this->actingAs($this->gerente)->post(route('caja.movimientos.contraasiento', $venta), ['motivo' => 'Error'])
            ->assertSessionHasErrorsIn('contraasiento', 'motivo');

        $this->assertFalse(CajaMovimiento::where('reversa_de_id', $venta->id)->exists());
    }
}
