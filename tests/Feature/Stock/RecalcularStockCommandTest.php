<?php

namespace Tests\Feature\Stock;

use App\Models\Auditoria;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\Variante;
use App\Services\MovimientosStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecalcularStockCommandTest extends TestCase
{
    use RefreshDatabase;

    private Stock $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $variante = Variante::factory()->create();
        $sucursal = Sucursal::factory()->create();
        DB::transaction(fn () => app(MovimientosStock::class)->ajustarAConteo($variante, $sucursal, 4, 'Stock inicial', now()));
        $this->stock = Stock::sole();
        Auditoria::query()->toBase()->delete();
    }

    public function test_it_reports_ok_when_stock_matches_the_kardex(): void
    {
        $this->artisan('stock:recalcular')
            ->expectsOutputToContain('El stock coincide con el kardex.')
            ->assertSuccessful();
    }

    public function test_it_reports_differences_without_changing_anything(): void
    {
        $this->desalinear(5);

        $this->artisan('stock:recalcular')
            ->expectsOutputToContain('Hay 1 diferencias')
            ->assertFailed();

        $this->assertSame(5, $this->stock->fresh()->cantidad);
        $this->assertSame(0, Auditoria::count());
    }

    public function test_it_fixes_and_audits_differences_when_asked(): void
    {
        $this->desalinear(5);

        $this->artisan('stock:recalcular --corregir')
            ->expectsOutputToContain('Se corrigieron 1 diferencias.')
            ->assertSuccessful();

        $this->assertSame(4, $this->stock->fresh()->cantidad);
        $registro = Auditoria::where('accion', 'stock.recalculado')->sole();
        $this->assertSame(['cantidad' => 5], $registro->antes);
        $this->assertSame(['cantidad' => 4], $registro->despues);
    }

    public function test_stock_rows_without_movements_must_be_zero(): void
    {
        $sinMovimientos = Stock::factory()->create(['cantidad' => 3]);

        $this->artisan('stock:recalcular --corregir')->assertSuccessful();

        $this->assertSame(0, $sinMovimientos->fresh()->cantidad);
    }

    private function desalinear(int $cantidad): void
    {
        DB::table('stock')->where('id', $this->stock->id)->update(['cantidad' => $cantidad]);
    }
}
