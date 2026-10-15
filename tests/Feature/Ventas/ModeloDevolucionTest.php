<?php

namespace Tests\Feature\Ventas;

use App\Enums\ResolucionDevolucion;
use App\Enums\TipoComprobante;
use App\Models\Comprobante;
use App\Models\Devolucion;
use App\Models\DevolucionItem;
use App\Models\VentaItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Support\EscenarioVentas;
use Tests\TestCase;

class ModeloDevolucionTest extends TestCase
{
    use EscenarioVentas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararEscenarioVentas();
        $this->abrirCaja($this->vendedorUser);
        $this->operarComo($this->vendedorUser);
    }

    public function test_devolucion_and_devolucion_items_are_immutable(): void
    {
        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 2]],
            [$this->pago($this->efectivo, '24200')]
        );

        $ventaItem = $venta->items()->first();

        $devolucion = Devolucion::create([
            'sucursal_id' => $this->sucursal->id,
            'venta_id' => $venta->id,
            'cliente_id' => null,
            'fecha' => now()->toDateString(),
            'motivo' => 'Cambio de talle',
            'resolucion' => ResolucionDevolucion::SaldoAFavor,
            'importe' => '12100.00',
            'neto' => '10000.00',
            'iva' => '2100.00',
            'user_id' => $this->vendedorUser->id,
            'dispositivo' => self::DISPOSITIVO,
        ]);

        $devolucionItem = DevolucionItem::create([
            'devolucion_id' => $devolucion->id,
            'venta_item_id' => $ventaItem->id,
            'variante_id' => $this->negro37->id,
            'cantidad' => 1,
            'importe' => '12100.00',
            'neto' => '10000.00',
            'iva' => '2100.00',
        ]);

        try {
            $devolucion->update(['motivo' => 'Otro motivo']);
            $this->fail('Se esperaba LogicException al actualizar Devolucion.');
        } catch (LogicException $e) {
            $this->assertSame('Una devolución no se puede modificar.', $e->getMessage());
        }

        try {
            $devolucion->delete();
            $this->fail('Se esperaba LogicException al borrar Devolucion.');
        } catch (LogicException $e) {
            $this->assertSame('Las devoluciones no se pueden borrar.', $e->getMessage());
        }

        try {
            $devolucionItem->update(['cantidad' => 2]);
            $this->fail('Se esperaba LogicException al actualizar DevolucionItem.');
        } catch (LogicException $e) {
            $this->assertSame('Los ítems de una devolución no se pueden modificar.', $e->getMessage());
        }

        try {
            $devolucionItem->delete();
            $this->fail('Se esperaba LogicException al borrar DevolucionItem.');
        } catch (LogicException $e) {
            $this->assertSame('Los ítems de una devolución no se pueden borrar.', $e->getMessage());
        }
    }

    public function test_relationships_between_devolucion_items_sales_and_comprobantes(): void
    {
        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 2]],
            [$this->pago($this->efectivo, '24200')]
        );

        $ventaItem = $venta->items()->first();

        $devolucion = Devolucion::create([
            'sucursal_id' => $this->sucursal->id,
            'venta_id' => $venta->id,
            'cliente_id' => null,
            'fecha' => now()->toDateString(),
            'motivo' => 'Defecto de fábrica',
            'resolucion' => ResolucionDevolucion::Reembolso,
            'importe' => '12100.00',
            'neto' => '10000.00',
            'iva' => '2100.00',
            'medio_pago_id' => $this->efectivo->id,
            'caja_sesion_id' => $venta->caja_sesion_id,
            'user_id' => $this->vendedorUser->id,
            'dispositivo' => self::DISPOSITIVO,
        ]);

        $devolucionItem = DevolucionItem::create([
            'devolucion_id' => $devolucion->id,
            'venta_item_id' => $ventaItem->id,
            'variante_id' => $this->negro37->id,
            'cantidad' => 1,
            'importe' => '12100.00',
            'neto' => '10000.00',
            'iva' => '2100.00',
        ]);

        $comprobante = Comprobante::create([
            'sucursal_id' => $this->sucursal->id,
            'venta_id' => $venta->id,
            'devolucion_id' => $devolucion->id,
            'tipo' => TipoComprobante::NotaCreditoInterna,
            'punto_venta_id' => $this->puntoVenta->id,
            'numero' => 1,
            'fecha' => now()->toDateString(),
            'emisor' => 'interno',
            'datos_json' => ['numero_completo' => '00001-00000001'],
        ]);

        // Devolucion relationships
        $this->assertTrue($devolucion->sucursal->is($this->sucursal));
        $this->assertTrue($devolucion->venta->is($venta));
        $this->assertNull($devolucion->cliente);
        $this->assertTrue($devolucion->medioPago->is($this->efectivo));
        $this->assertSame($venta->caja_sesion_id, $devolucion->cajaSesion->id);
        $this->assertTrue($devolucion->user->is($this->vendedorUser));
        $this->assertCount(1, $devolucion->items);
        $this->assertTrue($devolucion->items->first()->is($devolucionItem));
        $this->assertTrue($devolucion->notaCredito->is($comprobante));

        // DevolucionItem relationships
        $this->assertTrue($devolucionItem->devolucion->is($devolucion));
        $this->assertTrue($devolucionItem->ventaItem->is($ventaItem));
        $this->assertTrue($devolucionItem->variante->is($this->negro37));

        // Venta and VentaItem relationships
        $this->assertTrue($venta->devoluciones->contains($devolucion));
        $this->assertTrue($ventaItem->devolucionItems->contains($devolucionItem));

        // Comprobante relationships
        $this->assertTrue($comprobante->devolucion->is($devolucion));
    }

    public function test_foreign_key_on_comprobantes_devolucion_id(): void
    {
        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 2]],
            [$this->pago($this->efectivo, '24200')]
        );

        // Cannot reference a non-existent devolucion_id
        $this->expectException(QueryException::class);
        Comprobante::create([
            'sucursal_id' => $this->sucursal->id,
            'venta_id' => $venta->id,
            'devolucion_id' => 999999,
            'tipo' => TipoComprobante::NotaCreditoInterna,
            'punto_venta_id' => $this->puntoVenta->id,
            'numero' => 1,
            'fecha' => now()->toDateString(),
            'emisor' => 'interno',
            'datos_json' => ['numero_completo' => '00001-00000001'],
        ]);
    }

    public function test_foreign_key_restricts_deletion_of_referenced_devolucion(): void
    {
        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 2]],
            [$this->pago($this->efectivo, '24200')]
        );

        $devolucion = Devolucion::create([
            'sucursal_id' => $this->sucursal->id,
            'venta_id' => $venta->id,
            'cliente_id' => null,
            'fecha' => now()->toDateString(),
            'motivo' => 'Defecto de fábrica',
            'resolucion' => ResolucionDevolucion::SaldoAFavor,
            'importe' => '12100.00',
            'neto' => '10000.00',
            'iva' => '2100.00',
            'user_id' => $this->vendedorUser->id,
            'dispositivo' => self::DISPOSITIVO,
        ]);

        Comprobante::create([
            'sucursal_id' => $this->sucursal->id,
            'venta_id' => $venta->id,
            'devolucion_id' => $devolucion->id,
            'tipo' => TipoComprobante::NotaCreditoInterna,
            'punto_venta_id' => $this->puntoVenta->id,
            'numero' => 1,
            'fecha' => now()->toDateString(),
            'emisor' => 'interno',
            'datos_json' => ['numero_completo' => '00001-00000001'],
        ]);

        // Attempt direct DB delete of devolucion to bypass Eloquent event and verify DB FK constraint
        $this->expectException(QueryException::class);
        DB::table('devoluciones')->where('id', $devolucion->id)->delete();
    }
}
