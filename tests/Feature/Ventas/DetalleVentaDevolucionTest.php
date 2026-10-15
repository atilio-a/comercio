<?php

namespace Tests\Feature\Ventas;

use App\Enums\ResolucionDevolucion;
use App\Models\Cliente;
use App\Services\Devoluciones;
use App\Services\Ventas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EscenarioVentas;
use Tests\TestCase;

class DetalleVentaDevolucionTest extends TestCase
{
    use EscenarioVentas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararEscenarioVentas();
        $this->abrirCaja($this->vendedorUser);
        $this->operarComo($this->vendedorUser);
    }

    public function test_sale_detail_shows_devolver_cambiar_button_and_returned_quantities(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Marta Lopez']);

        $venta = $this->vender(
            [
                ['variante_id' => $this->negro37->id, 'cantidad' => 2],
                ['variante_id' => $this->media->id, 'cantidad' => 1],
            ],
            [$this->pago($this->efectivo, '35250')],
            $cliente,
        );

        $itemBota = $venta->items()->where('variante_id', $this->negro37->id)->first();

        // 1. Antes de devolver: botón presente, no muestra "Devueltas: 1"
        $this->actingAs($this->vendedorUser)
            ->get(route('ventas.show', $venta))
            ->assertOk()
            ->assertSee('Devolver / cambiar')
            ->assertSee(route('ventas.devoluciones.create', $venta))
            ->assertDontSee('Devueltas:');

        // 2. Registrar devolución parcial de 1 bota
        $devolucion = app(Devoluciones::class)->registrar(
            $venta,
            [$itemBota->id => 1],
            'Talle chico',
            ResolucionDevolucion::SaldoAFavor,
        );

        // 3. Después de devolver: muestra "Devueltas: 1" y la sección de devoluciones
        $this->actingAs($this->vendedorUser)
            ->get(route('ventas.show', $venta))
            ->assertOk()
            ->assertSee('Devolver / cambiar')
            ->assertSee('Devueltas: 1')
            ->assertSee('Devolución N° '.$devolucion->id)
            ->assertSee(route('ventas.devoluciones.show', $devolucion))
            ->assertSee('Talle chico');
    }

    public function test_sale_detail_hides_devolver_cambiar_button_when_anulada(): void
    {
        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '12100')],
        );

        app(Ventas::class)->anular($venta, 'Anulación por prueba');

        $this->actingAs($this->vendedorUser)
            ->get(route('ventas.show', $venta))
            ->assertOk()
            ->assertSee('Anulada')
            ->assertDontSee('Devolver / cambiar');
    }
}
