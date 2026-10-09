<?php

namespace Tests\Feature\Caja;

use App\Enums\EstadoSesionCaja;
use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Models\CajaSesionArqueo;
use App\Models\MedioPago;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ModeloCajaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_cash_movement_cannot_be_updated(): void
    {
        $movimiento = CajaMovimiento::factory()->create();

        $this->expectException(LogicException::class);

        $movimiento->update(['importe' => '1.00']);
    }

    public function test_a_cash_movement_cannot_be_deleted(): void
    {
        $movimiento = CajaMovimiento::factory()->create();

        try {
            $movimiento->delete();
            $this->fail('Se esperaba LogicException.');
        } catch (LogicException) {
            $this->assertDatabaseHas('caja_movimientos', ['id' => $movimiento->id]);
        }
    }

    public function test_a_count_cannot_be_updated_or_deleted(): void
    {
        $arqueo = CajaSesionArqueo::create([
            'caja_sesion_id' => CajaSesion::factory()->cerrada()->create()->id,
            'medio_pago_id' => MedioPago::factory()->efectivo()->create()->id,
            'esperado' => '10.00',
            'contado' => '10.00',
            'diferencia' => '0.00',
        ]);

        $this->assertThrows(fn () => $arqueo->update(['contado' => '5.00']), LogicException::class);
        $this->assertThrows(fn () => $arqueo->delete(), LogicException::class);
    }

    public function test_a_closed_session_cannot_be_modified_and_no_session_can_be_deleted(): void
    {
        $cerrada = CajaSesion::factory()->cerrada()->create();
        $abierta = CajaSesion::factory()->create();

        $this->assertThrows(fn () => $cerrada->update(['justificacion' => 'Cambio']), LogicException::class);
        $this->assertThrows(fn () => $abierta->delete(), LogicException::class);
    }

    public function test_the_database_rejects_a_second_open_session_for_the_same_cash_register(): void
    {
        $caja = Caja::factory()->create();
        CajaSesion::factory()->for($caja)->create();

        $this->expectException(QueryException::class);

        CajaSesion::factory()->for($caja)->create();
    }

    public function test_closed_sessions_do_not_block_a_new_open_session(): void
    {
        $caja = Caja::factory()->create();
        $user = User::factory()->create();
        CajaSesion::factory()->for($caja)->for($user)->cerrada()->create();
        CajaSesion::factory()->for($caja)->for($user)->cerrada()->create();

        $abierta = CajaSesion::factory()->for($caja)->for($user)->create();

        $this->assertSame(EstadoSesionCaja::Abierta, $abierta->fresh()->estado);
    }

    public function test_the_database_rejects_two_open_sessions_of_the_same_user_in_a_branch(): void
    {
        $user = User::factory()->create();
        $caja = Caja::factory()->create();
        $otra = Caja::factory()->create(['sucursal_id' => $caja->sucursal_id]);
        CajaSesion::factory()->for($caja)->for($user)->create();

        $this->expectException(QueryException::class);

        CajaSesion::factory()->for($otra)->for($user)->create();
    }
}
