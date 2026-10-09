<?php

namespace Tests\Feature\Stock;

use App\Enums\Parametro;
use App\Enums\TipoMovimientoStock;
use App\Exceptions\StockInsuficiente;
use App\Exceptions\TransferenciaNoHabilitada;
use App\Http\Middleware\IdentificarDispositivo;
use App\Models\Auditoria;
use App\Models\MovimientoStock;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Variante;
use App\Services\MovimientosStock;
use App\Services\Parametros;
use App\Services\SucursalActiva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class MovimientosStockTest extends TestCase
{
    use RefreshDatabase;

    private Variante $variante;

    private Sucursal $sucursal;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->variante = Variante::factory()->create(['talle' => '37']);
        $this->sucursal = Sucursal::factory()->create();
        $this->user = User::factory()->conSucursal($this->sucursal)->create();

        $this->actingAs($this->user);
        app(SucursalActiva::class)->establecer($this->sucursal);
        request()->attributes->set(IdentificarDispositivo::ATRIBUTO, '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d');
    }

    public function test_balances_are_chained_and_the_stock_follows_the_last_movement(): void
    {
        $this->mover(TipoMovimientoStock::Ajuste, 3, 'Stock inicial');
        $egreso = $this->mover(TipoMovimientoStock::Egreso, -1);
        $ajuste = $this->mover(TipoMovimientoStock::Ajuste, 4, 'Recuento');

        $this->assertSame(2, $egreso->saldo);
        $this->assertSame(6, $ajuste->saldo);
        $this->assertSame(6, $this->cantidad());
        $this->assertSame($this->user->id, $ajuste->user_id);
        $this->assertSame('9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d', $ajuste->dispositivo);
        $this->assertSame(now()->toDateString(), $ajuste->fecha->toDateString());
    }

    public function test_a_movement_can_reference_its_origin_and_business_date(): void
    {
        $origen = User::factory()->create();

        $movimiento = DB::transaction(fn () => app(MovimientosStock::class)->registrar(
            $this->variante, $this->sucursal, TipoMovimientoStock::Ingreso, 2, 'Recepción', $origen, now()->subDay(),
        ));

        $this->assertSame(User::class, $movimiento->origen_type);
        $this->assertSame($origen->id, $movimiento->origen_id);
        $this->assertSame(now()->subDay()->toDateString(), $movimiento->fresh()->fecha->toDateString());
    }

    public function test_zero_quantity_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->mover(TipoMovimientoStock::Ajuste, 0);
    }

    public function test_quantity_sign_must_match_the_type(): void
    {
        try {
            $this->mover(TipoMovimientoStock::Ingreso, -1);
            $this->fail('Un ingreso negativo debía rechazarse.');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);
        $this->mover(TipoMovimientoStock::Egreso, 1);
    }

    public function test_transfers_are_rejected(): void
    {
        $this->expectException(TransferenciaNoHabilitada::class);

        $this->mover(TipoMovimientoStock::Transferencia, 1);
    }

    public function test_movements_require_a_transaction(): void
    {
        // RefreshDatabase wraps each test in a transaction, so simulate level 0.
        DB::partialMock()->shouldReceive('transactionLevel')->andReturn(0);

        $this->expectException(LogicException::class);

        app(MovimientosStock::class)->registrar($this->variante, $this->sucursal, TipoMovimientoStock::Ajuste, 1);
    }

    public function test_outflow_without_stock_is_rejected_when_negative_stock_is_disabled(): void
    {
        $this->mover(TipoMovimientoStock::Ajuste, 1);

        try {
            $this->mover(TipoMovimientoStock::Egreso, -2);
            $this->fail('El egreso debía rechazarse.');
        } catch (StockInsuficiente $error) {
            $this->assertSame(1, $error->disponible);
            $this->assertStringContainsString('hay 1 disponibles', $error->getMessage());
        }

        $this->assertSame(1, $this->cantidad());
        $this->assertSame(1, MovimientoStock::count());
    }

    public function test_outflow_without_stock_is_recorded_and_audited_when_negative_stock_is_enabled(): void
    {
        app(Parametros::class)->guardar(Parametro::StockNegativo, true);
        $this->mover(TipoMovimientoStock::Ajuste, 1);

        $egreso = $this->mover(TipoMovimientoStock::Egreso, -2);

        $this->assertSame(-1, $egreso->saldo);
        $this->assertSame(-1, $this->cantidad());
        $registro = Auditoria::where('accion', 'stock.negativo')->sole();
        $this->assertSame($this->user->id, $registro->user_id);
        $this->assertSame($this->sucursal->id, $registro->sucursal_id);
        $this->assertSame(['cantidad' => 1], $registro->antes);
        $this->assertSame(-1, $registro->despues['saldo']);
        $this->assertSame($this->variante->id, $registro->despues['variante_id']);
    }

    public function test_consecutive_outflows_use_the_updated_balance(): void
    {
        $this->mover(TipoMovimientoStock::Ajuste, 1);

        $primero = $this->mover(TipoMovimientoStock::Egreso, -1);

        try {
            $this->mover(TipoMovimientoStock::Egreso, -1);
            $this->fail('El segundo egreso debía rechazarse.');
        } catch (StockInsuficiente $error) {
            $this->assertSame(0, $error->disponible);
        }

        $this->assertSame(0, $primero->saldo);
        $this->assertSame(0, $this->cantidad());
    }

    public function test_a_rejected_outflow_rolls_back_the_whole_operation(): void
    {
        $this->mover(TipoMovimientoStock::Ajuste, 1);
        $otra = Variante::factory()->create();

        try {
            DB::transaction(function () use ($otra) {
                $servicio = app(MovimientosStock::class);
                $servicio->registrar($otra, $this->sucursal, TipoMovimientoStock::Ajuste, 5);
                $servicio->registrar($this->variante, $this->sucursal, TipoMovimientoStock::Egreso, -2);
            });
        } catch (StockInsuficiente) {
        }

        $this->assertSame(0, Stock::where('variante_id', $otra->id)->value('cantidad') ?? 0);
        $this->assertSame(1, MovimientoStock::count());
    }

    public function test_initial_count_creates_an_adjustment(): void
    {
        $movimiento = $this->contar(4, 'Stock inicial');

        $this->assertSame(TipoMovimientoStock::Ajuste, $movimiento->tipo);
        $this->assertSame(4, $movimiento->cantidad);
        $this->assertSame('Stock inicial', $movimiento->motivo);
        $this->assertSame(4, $this->cantidad());
    }

    public function test_count_lower_than_stock_creates_a_negative_adjustment_and_is_audited(): void
    {
        $this->contar(4, 'Stock inicial');
        Auditoria::query()->toBase()->delete();

        $movimiento = $this->contar(3, 'Recuento');

        $this->assertSame(-1, $movimiento->cantidad);
        $this->assertSame(3, $this->cantidad());
        $registro = Auditoria::where('accion', 'stock.ajuste')->sole();
        $this->assertSame(['cantidad' => 4], $registro->antes);
        $this->assertSame(3, $registro->despues['cantidad']);
        $this->assertSame('Recuento', $registro->despues['motivo']);
        $this->assertSame($this->sucursal->id, $registro->sucursal_id);
    }

    public function test_count_without_difference_records_nothing(): void
    {
        $this->contar(4, 'Stock inicial');
        $movimientos = MovimientoStock::count();
        $auditorias = Auditoria::count();

        $this->assertNull($this->contar(4, 'Recuento'));

        $this->assertSame($movimientos, MovimientoStock::count());
        $this->assertSame($auditorias, Auditoria::count());
    }

    public function test_minimum_is_updated_and_audited_without_movements(): void
    {
        DB::transaction(fn () => app(MovimientosStock::class)->actualizarMinimo($this->variante, $this->sucursal, 2));

        $stock = Stock::where('variante_id', $this->variante->id)->sole();
        $this->assertSame(2, $stock->minimo);
        $this->assertSame(0, MovimientoStock::count());
        $registro = Auditoria::where('accion', 'stock.minimo_actualizado')->sole();
        $this->assertSame(['minimo' => 0], $registro->antes);
        $this->assertSame(['minimo' => 2], $registro->despues);
    }

    public function test_stock_is_kept_per_branch(): void
    {
        $otra = Sucursal::factory()->create();
        $this->contar(5, 'Stock inicial');

        DB::transaction(fn () => app(MovimientosStock::class)->ajustarAConteo($this->variante, $otra, 2, 'Stock inicial', now()));

        $this->assertSame(5, $this->cantidad());
        $this->assertSame(2, Stock::where('variante_id', $this->variante->id)->where('sucursal_id', $otra->id)->value('cantidad'));
    }

    private function mover(TipoMovimientoStock $tipo, int $cantidad, ?string $motivo = null): MovimientoStock
    {
        return DB::transaction(fn () => app(MovimientosStock::class)->registrar($this->variante, $this->sucursal, $tipo, $cantidad, $motivo));
    }

    private function contar(int $contado, string $motivo): ?MovimientoStock
    {
        return DB::transaction(fn () => app(MovimientosStock::class)->ajustarAConteo($this->variante, $this->sucursal, $contado, $motivo, now()));
    }

    private function cantidad(): int
    {
        return Stock::where('variante_id', $this->variante->id)->where('sucursal_id', $this->sucursal->id)->value('cantidad');
    }
}
