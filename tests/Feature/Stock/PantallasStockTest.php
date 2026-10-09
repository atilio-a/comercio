<?php

namespace Tests\Feature\Stock;

use App\Enums\Parametro;
use App\Enums\Rol;
use App\Enums\TipoMovimientoStock;
use App\Models\Auditoria;
use App\Models\Color;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Variante;
use App\Services\MovimientosStock;
use App\Services\Parametros;
use App\Services\SucursalActiva;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PantallasStockTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $deposito;

    private Producto $producto;

    private Variante $negro37;

    private Variante $negro38;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->sucursal = Sucursal::factory()->create(['nombre' => Sucursal::PRINCIPAL]);
        $this->deposito = User::factory()->conSucursal($this->sucursal, Rol::Deposito)->create();
        $this->producto = Producto::factory()->create(['nombre' => 'Bota Ana']);
        $negro = Color::factory()->create(['nombre' => 'Negro']);
        $this->negro37 = Variante::factory()->for($this->producto)->for($negro)->create(['talle' => '37']);
        $this->negro38 = Variante::factory()->for($this->producto)->for($negro)->create(['talle' => '38']);
    }

    public function test_variants_without_stock_rows_are_listed_with_zero(): void
    {
        $this->actingAs($this->deposito)->get(route('stock.index'))
            ->assertOk()
            ->assertSee('Bota Ana')
            ->assertSee('Negro / 37')
            ->assertViewHas('variantes', fn ($variantes) => $variantes->pluck('stock_cantidad')->map(fn ($c) => (int) $c)->all() === [0, 0]);

        $this->assertSame(0, Stock::count());
    }

    public function test_stock_shown_is_the_one_of_the_active_branch(): void
    {
        $otra = Sucursal::factory()->create();
        $this->contar($this->negro37, 5, $otra);
        $this->contar($this->negro37, 2);

        $this->actingAs($this->deposito)->get(route('stock.index', ['q' => $this->negro37->sku]))
            ->assertOk()
            ->assertViewHas('variantes', fn ($variantes) => $variantes->pluck('stock_cantidad')->map(fn ($c) => (int) $c)->all() === [2]);
    }

    public function test_below_minimum_filter(): void
    {
        $this->contar($this->negro37, 1, minimo: 2);
        $this->contar($this->negro38, 5, minimo: 2);

        $this->actingAs($this->deposito)->get(route('stock.index', ['bajo_minimo' => 1]))
            ->assertOk()
            ->assertViewHas('variantes', fn ($variantes) => $variantes->pluck('id')->all() === [$this->negro37->id])
            ->assertSee('Bajo mínimo');
    }

    public function test_negative_stock_is_highlighted(): void
    {
        app(Parametros::class)->guardar(Parametro::StockNegativo, true);
        $this->actingAs($this->deposito);
        DB::transaction(fn () => app(MovimientosStock::class)->registrar($this->negro37, $this->sucursal, TipoMovimientoStock::Egreso, -1));

        $this->get(route('stock.index'))->assertOk()->assertSee('Stock negativo');
    }

    public function test_salesperson_sees_stock_without_adjust_links(): void
    {
        $vendedor = User::factory()->conSucursal($this->sucursal, Rol::Vendedor)->create();

        $this->actingAs($vendedor)->get(route('stock.index'))
            ->assertOk()
            ->assertDontSee(route('stock.ajuste.edit', $this->producto));
        $this->actingAs($vendedor)->get(route('stock.ajuste.edit', $this->producto))->assertForbidden();
    }

    public function test_initial_load_of_two_variants(): void
    {
        $this->actingAs($this->deposito)
            ->put(route('stock.ajuste.update', $this->producto), [
                'contado' => [$this->negro37->id => '4', $this->negro38->id => '2'],
                'motivo' => 'Stock inicial',
                'fecha' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Se registraron 2 ajustes.');

        $this->assertSame(4, $this->cantidad($this->negro37));
        $this->assertSame(2, $this->cantidad($this->negro38));
        $this->assertSame(['Stock inicial'], MovimientoStock::distinct()->pluck('motivo')->all());
        $this->assertSame(2, Auditoria::where('accion', 'stock.ajuste')->count());
    }

    public function test_empty_counts_do_not_create_movements(): void
    {
        $this->actingAs($this->deposito)
            ->put(route('stock.ajuste.update', $this->producto), [
                'contado' => [$this->negro37->id => '3', $this->negro38->id => ''],
                'motivo' => 'Stock inicial',
                'fecha' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, MovimientoStock::count());
        $this->assertSame(0, $this->cantidad($this->negro38));
    }

    public function test_reason_is_required_when_counting(): void
    {
        $this->actingAs($this->deposito)
            ->put(route('stock.ajuste.update', $this->producto), [
                'contado' => [$this->negro37->id => '3'],
                'motivo' => '',
                'fecha' => now()->toDateString(),
            ])
            ->assertSessionHasErrors(['motivo' => 'Indicá el motivo del ajuste.']);

        $this->assertSame(0, MovimientoStock::count());
    }

    public function test_negative_counts_and_future_dates_are_rejected(): void
    {
        $this->actingAs($this->deposito)
            ->put(route('stock.ajuste.update', $this->producto), [
                'contado' => [$this->negro37->id => '-1'],
                'motivo' => 'Recuento',
                'fecha' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('contado.'.$this->negro37->id);

        $this->actingAs($this->deposito)
            ->put(route('stock.ajuste.update', $this->producto), [
                'contado' => [$this->negro37->id => '1'],
                'motivo' => 'Recuento',
                'fecha' => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors(['fecha' => 'La fecha no puede ser futura.']);

        $this->assertSame(0, MovimientoStock::count());
    }

    public function test_variants_of_another_product_are_rejected(): void
    {
        $ajena = Variante::factory()->create();

        $this->actingAs($this->deposito)
            ->put(route('stock.ajuste.update', $this->producto), [
                'contado' => [$ajena->id => '5'],
                'motivo' => 'Recuento',
                'fecha' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('contado');

        $this->assertSame(0, MovimientoStock::count());
    }

    public function test_minimum_can_be_updated_without_a_reason_or_movements(): void
    {
        $this->actingAs($this->deposito)
            ->put(route('stock.ajuste.update', $this->producto), [
                'minimo' => [$this->negro37->id => '2'],
                'fecha' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'No hubo diferencias de stock.');

        $this->assertSame(2, Stock::where('variante_id', $this->negro37->id)->value('minimo'));
        $this->assertSame(0, MovimientoStock::count());
    }

    public function test_cashier_cannot_adjust(): void
    {
        $cajero = User::factory()->conSucursal($this->sucursal, Rol::Cajero)->create();

        $this->actingAs($cajero)
            ->put(route('stock.ajuste.update', $this->producto), [
                'contado' => [$this->negro37->id => '4'],
                'motivo' => 'Stock inicial',
                'fecha' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertSame(0, MovimientoStock::count());
    }

    public function test_adjust_screen_renders_current_stock(): void
    {
        $this->contar($this->negro37, 4);

        $this->actingAs($this->deposito)->get(route('stock.ajuste.edit', $this->producto))
            ->assertOk()
            ->assertSee('Negro / 37')
            ->assertSee('name="contado['.$this->negro37->id.']"', false);
    }

    public function test_kardex_lists_movements_from_newest_to_oldest(): void
    {
        $this->contar($this->negro37, 4, motivo: 'Stock inicial');
        $this->contar($this->negro37, 3, motivo: 'Recuento');

        $this->actingAs($this->deposito)->get(route('stock.kardex', $this->negro37))
            ->assertOk()
            ->assertSee('Negro / 37')
            ->assertSeeInOrder(['Recuento', 'Stock inicial'])
            ->assertViewHas('movimientos', fn ($movimientos) => $movimientos->pluck('saldo')->all() === [3, 4]);
    }

    public function test_kardex_can_be_filtered_by_business_date(): void
    {
        $this->contar($this->negro37, 4, motivo: 'Viejo', fecha: now()->subDays(10));
        $this->contar($this->negro37, 3, motivo: 'Reciente');

        $this->actingAs($this->deposito)
            ->get(route('stock.kardex', [$this->negro37, 'desde' => now()->subDay()->toDateString()]))
            ->assertOk()
            ->assertViewHas('movimientos', fn ($movimientos) => $movimientos->pluck('motivo')->all() === ['Reciente']);
    }

    public function test_kardex_only_shows_the_active_branch(): void
    {
        $otra = Sucursal::factory()->create();
        $this->contar($this->negro37, 5, $otra, motivo: 'Otra sucursal');
        $this->contar($this->negro37, 2, motivo: 'Esta sucursal');

        $this->actingAs($this->deposito)->get(route('stock.kardex', $this->negro37))
            ->assertOk()
            ->assertViewHas('movimientos', fn ($movimientos) => $movimientos->pluck('motivo')->all() === ['Esta sucursal']);
    }

    public function test_users_without_stock_permission_get_forbidden(): void
    {
        $sinRol = User::factory()->conSucursal($this->sucursal)->create();

        $this->actingAs($sinRol)->get(route('stock.index'))->assertForbidden();
        $this->actingAs($sinRol)->get(route('stock.kardex', $this->negro37))->assertForbidden();
    }

    private function contar(Variante $variante, int $cantidad, ?Sucursal $sucursal = null, int $minimo = 0, string $motivo = 'Stock inicial', $fecha = null): void
    {
        $sucursal ??= $this->sucursal;
        app(SucursalActiva::class)->establecer($sucursal);

        DB::transaction(function () use ($variante, $sucursal, $cantidad, $minimo, $motivo, $fecha) {
            $servicio = app(MovimientosStock::class);
            $servicio->ajustarAConteo($variante, $sucursal, $cantidad, $motivo, $fecha ?? now());
            $servicio->actualizarMinimo($variante, $sucursal, $minimo);
        });
    }

    private function cantidad(Variante $variante): int
    {
        return (int) Stock::where('variante_id', $variante->id)->where('sucursal_id', $this->sucursal->id)->value('cantidad');
    }
}
