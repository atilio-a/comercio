<?php

namespace Tests\Feature\Stock;

use App\Models\MovimientoStock;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\Variante;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ModeloStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_there_is_one_stock_row_per_variant_and_branch(): void
    {
        $variante = Variante::factory()->create();
        $sucursal = Sucursal::factory()->create();
        Stock::factory()->for($variante)->for($sucursal)->create();
        Stock::factory()->for($variante)->for(Sucursal::factory())->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Stock::factory()->for($variante)->for($sucursal)->create();
    }

    public function test_stock_quantity_is_not_mass_assignable(): void
    {
        $stock = Stock::factory()->create(['cantidad' => 5]);

        try {
            $stock->fill(['cantidad' => 99]);
            $this->fail('Se esperaba que la cantidad no se pudiera asignar en masa.');
        } catch (MassAssignmentException) {
            $this->assertSame(5, $stock->cantidad);
        }
    }

    public function test_movements_cannot_be_updated(): void
    {
        $movimiento = MovimientoStock::factory()->create();

        $this->expectException(LogicException::class);

        $movimiento->update(['cantidad' => 10]);
    }

    public function test_movements_cannot_be_deleted(): void
    {
        $movimiento = MovimientoStock::factory()->create();

        try {
            $movimiento->delete();
            $this->fail('Se esperaba una excepción al borrar.');
        } catch (LogicException) {
            $this->assertModelExists($movimiento);
        }
    }
}
