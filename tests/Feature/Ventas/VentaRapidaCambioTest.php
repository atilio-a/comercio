<?php

namespace Tests\Feature\Ventas;

use App\Enums\ResolucionDevolucion;
use App\Models\Cliente;
use App\Services\Devoluciones;
use App\Services\Ventas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EscenarioVentas;
use Tests\TestCase;

class VentaRapidaCambioTest extends TestCase
{
    use EscenarioVentas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararEscenarioVentas();
        $this->abrirCaja($this->vendedorUser);
        $this->operarComo($this->vendedorUser);
    }

    public function test_devolucion_detail_has_button_to_use_saldo_in_new_sale(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Andrea Ruiz']);

        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '12100')],
            $cliente,
        );

        $devolucion = app(Devoluciones::class)->registrar(
            $venta,
            [$venta->items->first()->id => 1],
            'Cambio por talle 38',
            ResolucionDevolucion::SaldoAFavor,
        );

        $this->actingAs($this->vendedorUser)
            ->get(route('ventas.devoluciones.show', $devolucion))
            ->assertOk()
            ->assertSee('Usar el saldo en una nueva venta')
            ->assertSee(route('ventas.create', ['cliente_id' => $cliente->id]));
    }

    public function test_quick_sale_preloads_customer_and_saldo_a_favor(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Andrea Ruiz']);

        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '12100')],
            $cliente,
        );

        app(Devoluciones::class)->registrar(
            $venta,
            [$venta->items->first()->id => 1],
            'Cambio por talle 38',
            ResolucionDevolucion::SaldoAFavor,
        );

        $this->assertSame('12100.00', $cliente->fresh()->cuentaCorriente->saldoAFavor());

        $this->actingAs($this->vendedorUser)
            ->get(route('ventas.create', ['cliente_id' => $cliente->id]))
            ->assertOk()
            ->assertSee('Andrea Ruiz')
            ->assertSee($this->enConfiguracion('"saldo_a_favor":"12100.00"'), false)
            ->assertSee($this->enConfiguracion('"nombre":"Andrea Ruiz"'), false);
    }

    public function test_new_sale_registered_paying_with_saldo_a_favor_from_devolucion(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Andrea Ruiz']);

        // 1. Venta original
        $venta1 = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '12100')],
            $cliente,
        );

        // 2. Devolución con saldo a favor
        app(Devoluciones::class)->registrar(
            $venta1,
            [$venta1->items->first()->id => 1],
            'Cambio por talle 38',
            ResolucionDevolucion::SaldoAFavor,
        );

        $this->assertSame('12100.00', $cliente->fresh()->cuentaCorriente->saldoAFavor());

        // 3. Nueva venta del talle 38 pagada con el saldo a favor
        $ventaNueva = app(Ventas::class)->registrar(
            $this->sucursal,
            $this->puntoVenta,
            $this->vendedor,
            $cliente,
            [['variante_id' => $this->negro38->id, 'cantidad' => 1]],
            0,
            [$this->pago($this->saldoAFavor, '12100')],
        );

        $this->assertSame('12100.00', (string) $ventaNueva->total);
        $this->assertSame('0.00', $cliente->fresh()->cuentaCorriente->saldoAFavor());
        $this->assertSame('0.00', (string) $cliente->fresh()->cuentaCorriente->saldo);
    }

    private function enConfiguracion(string $json): string
    {
        return str_replace('"', chr(92).'u0022', $json);
    }
}
