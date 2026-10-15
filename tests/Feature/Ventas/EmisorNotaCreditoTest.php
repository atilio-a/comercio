<?php

namespace Tests\Feature\Ventas;

use App\Enums\ResolucionDevolucion;
use App\Enums\TipoComprobante;
use App\Exceptions\VentaRechazada;
use App\Models\Cliente;
use App\Models\Devolucion;
use App\Models\DevolucionItem;
use App\Services\Comprobantes\EmisorInterno;
use App\Services\Comprobantes\EmisorNoDisponible;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioVentas;
use Tests\TestCase;

class EmisorNotaCreditoTest extends TestCase
{
    use EscenarioVentas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararEscenarioVentas();
        $this->abrirCaja($this->vendedorUser);
        $this->operarComo($this->vendedorUser);
    }

    public function test_credit_note_has_own_correlative_numbering_fk_legend_and_copied_data(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'María López']);

        $venta = $this->vender(
            [
                ['variante_id' => $this->negro37->id, 'cantidad' => 2],
                ['variante_id' => $this->media->id, 'cantidad' => 1],
            ],
            [$this->pago($this->efectivo, '35250')],
            $cliente,
        );

        // Venta has receipt number 1
        $this->assertSame(1, $venta->comprobante->numero);

        // Another sale to increment sale receipt numbers
        $otraVenta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '12100')]
        );
        $this->assertSame(2, $otraVenta->comprobante->numero);

        $itemVenta = $venta->items()->where('variante_id', $this->negro37->id)->first();

        // Create first devolucion
        $devolucion1 = Devolucion::create([
            'sucursal_id' => $this->sucursal->id,
            'venta_id' => $venta->id,
            'cliente_id' => $cliente->id,
            'fecha' => now()->toDateString(),
            'motivo' => 'Talle chico',
            'resolucion' => ResolucionDevolucion::SaldoAFavor,
            'importe' => '12100.00',
            'neto' => '10000.00',
            'iva' => '2100.00',
            'user_id' => $this->vendedorUser->id,
            'dispositivo' => self::DISPOSITIVO,
        ]);

        DevolucionItem::create([
            'devolucion_id' => $devolucion1->id,
            'venta_item_id' => $itemVenta->id,
            'variante_id' => $this->negro37->id,
            'cantidad' => 1,
            'importe' => '12100.00',
            'neto' => '10000.00',
            'iva' => '2100.00',
        ]);

        /** @var EmisorInterno $emisor */
        $emisor = app(EmisorInterno::class);

        // Emit first credit note in a transaction (Numerador requires active transaction)
        $nota1 = DB::transaction(fn () => $emisor->emitirNotaCredito($devolucion1));

        $this->assertSame(TipoComprobante::NotaCreditoInterna, $nota1->tipo);
        $this->assertSame(1, $nota1->numero);
        $this->assertSame('00001-00000001', $nota1->numeroCompleto());
        $this->assertSame($devolucion1->id, $nota1->devolucion_id);
        $this->assertTrue($nota1->devolucion->is($devolucion1));
        $this->assertSame($venta->id, $nota1->venta_id);

        // Check snapshot data
        $datos1 = $nota1->datos_json;
        $this->assertSame('Nota de crédito interna', $datos1['titulo']);
        $this->assertSame('Documento no válido como factura', $datos1['leyenda']);
        $this->assertSame('interno', $datos1['emisor']);
        $this->assertSame($this->puntoVenta->numero, $datos1['punto_venta']);
        $this->assertSame(1, $datos1['numero']);
        $this->assertSame(config('app.name'), $datos1['comercio']);
        $this->assertSame($this->sucursal->nombre, $datos1['sucursal']['nombre']);
        $this->assertSame($cliente->nombre, $datos1['cliente']['nombre']);
        $this->assertSame($venta->vendedor->user->name, $datos1['vendedor']);

        $this->assertCount(1, $datos1['items']);
        $this->assertSame('12100.00', $datos1['items'][0]['total']);
        $this->assertSame('10000.00', $datos1['items'][0]['neto']);
        $this->assertSame('2100.00', $datos1['items'][0]['iva']);
        $this->assertSame(1, $datos1['items'][0]['cantidad']);

        $this->assertSame('12100.00', $datos1['totales']['total']);
        $this->assertSame('10000.00', $datos1['totales']['subtotal_neto']);
        $this->assertSame('2100.00', $datos1['totales']['iva']);

        $this->assertSame($devolucion1->id, $datos1['devolucion']['id']);
        $this->assertSame('Talle chico', $datos1['devolucion']['motivo']);
        $this->assertSame(ResolucionDevolucion::SaldoAFavor->etiqueta(), $datos1['devolucion']['resolucion']);
        $this->assertSame($venta->comprobante->numeroCompleto(), $datos1['referencia']['numero_completo']);

        // Create second devolucion and check correlative numbering
        $devolucion2 = Devolucion::create([
            'sucursal_id' => $this->sucursal->id,
            'venta_id' => $venta->id,
            'cliente_id' => $cliente->id,
            'fecha' => now()->toDateString(),
            'motivo' => 'Otro cambio',
            'resolucion' => ResolucionDevolucion::SaldoAFavor,
            'importe' => '12100.00',
            'neto' => '10000.00',
            'iva' => '2100.00',
            'user_id' => $this->vendedorUser->id,
            'dispositivo' => self::DISPOSITIVO,
        ]);

        DevolucionItem::create([
            'devolucion_id' => $devolucion2->id,
            'venta_item_id' => $itemVenta->id,
            'variante_id' => $this->negro37->id,
            'cantidad' => 1,
            'importe' => '12100.00',
            'neto' => '10000.00',
            'iva' => '2100.00',
        ]);

        $nota2 = DB::transaction(fn () => $emisor->emitirNotaCredito($devolucion2));

        $this->assertSame(TipoComprobante::NotaCreditoInterna, $nota2->tipo);
        $this->assertSame(2, $nota2->numero);
        $this->assertSame('00001-00000002', $nota2->numeroCompleto());
        $this->assertSame($devolucion2->id, $nota2->devolucion_id);
    }

    public function test_emisor_no_disponible_rejects_credit_note_emission(): void
    {
        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '12100')]
        );

        $devolucion = Devolucion::create([
            'sucursal_id' => $this->sucursal->id,
            'venta_id' => $venta->id,
            'cliente_id' => null,
            'fecha' => now()->toDateString(),
            'motivo' => 'Falla',
            'resolucion' => ResolucionDevolucion::Reembolso,
            'importe' => '12100.00',
            'neto' => '10000.00',
            'iva' => '2100.00',
            'user_id' => $this->vendedorUser->id,
            'dispositivo' => self::DISPOSITIVO,
        ]);

        $emisor = new EmisorNoDisponible;

        $this->expectException(VentaRechazada::class);
        $this->expectExceptionMessage(EmisorNoDisponible::MENSAJE);

        $emisor->emitirNotaCredito($devolucion);
    }
}
