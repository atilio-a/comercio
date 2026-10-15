<?php

namespace Tests\Feature\Ventas;

use App\Enums\ResolucionDevolucion;
use App\Enums\Rol;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Devolucion;
use App\Models\PuntoVenta;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Vendedor;
use App\Services\CuentasCorrientes;
use App\Services\Devoluciones;
use App\Services\SucursalActiva;
use App\Services\Ventas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioVentas;
use Tests\TestCase;

class DevolucionHttpTest extends TestCase
{
    use EscenarioVentas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararEscenarioVentas();
        $this->abrirCaja($this->vendedorUser);
        $this->operarComo($this->vendedorUser);
    }

    public function test_seller_returns_with_saldo_a_favor(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Lucia Vendedora']);

        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 2]],
            [$this->pago($this->efectivo, '24200')],
            $cliente,
        );

        $item = $venta->items->first();

        $this->actingAs($this->vendedorUser)
            ->get(route('ventas.devoluciones.create', $venta))
            ->assertOk()
            ->assertSee('Devolución / cambio')
            ->assertSee('Bota Ana');

        $respuesta = $this->actingAs($this->vendedorUser)
            ->post(route('ventas.devoluciones.store', $venta), [
                'cantidades' => [$item->id => 1],
                'motivo' => 'Talle chico',
                'resolucion' => ResolucionDevolucion::SaldoAFavor->value,
            ]);

        $devolucion = Devolucion::sole();
        $respuesta->assertRedirect(route('ventas.devoluciones.show', $devolucion));

        $this->assertSame('12100.00', (string) $devolucion->importe);
        $this->assertSame(ResolucionDevolucion::SaldoAFavor, $devolucion->resolucion);
        $this->assertSame($cliente->id, $devolucion->cliente_id);
        $this->assertNotNull($devolucion->notaCredito);

        $this->actingAs($this->vendedorUser)
            ->get(route('ventas.devoluciones.show', $devolucion))
            ->assertOk()
            ->assertSee('Devolución N° '.$devolucion->id)
            ->assertSee('Talle chico')
            ->assertSee('Lucia Vendedora');
    }

    public function test_seller_cannot_reimburse(): void
    {
        $cliente = Cliente::factory()->create();

        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '12100')],
            $cliente,
        );

        $item = $venta->items->first();

        $this->actingAs($this->vendedorUser)
            ->post(route('ventas.devoluciones.store', $venta), [
                'cantidades' => [$item->id => 1],
                'motivo' => 'Reembolso no permitido',
                'resolucion' => ResolucionDevolucion::Reembolso->value,
                'medio_pago_id' => $this->efectivo->id,
            ])
            ->assertSessionHasErrors(['resolucion']);

        $this->assertSame(0, Devolucion::count());
    }

    public function test_manager_can_reimburse_with_open_cash_register(): void
    {
        $this->abrirCaja($this->gerente);

        $cliente = Cliente::factory()->create();

        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '12100')],
            $cliente,
        );

        $item = $venta->items->first();

        $respuesta = $this->actingAs($this->gerente)
            ->post(route('ventas.devoluciones.store', $venta), [
                'cantidades' => [$item->id => 1],
                'motivo' => 'Reembolso por falla',
                'resolucion' => ResolucionDevolucion::Reembolso->value,
                'medio_pago_id' => $this->efectivo->id,
            ]);

        $devolucion = Devolucion::sole();
        $respuesta->assertRedirect(route('ventas.devoluciones.show', $devolucion));

        $this->assertSame(ResolucionDevolucion::Reembolso, $devolucion->resolucion);
        $this->assertSame($this->efectivo->id, $devolucion->medio_pago_id);
    }

    public function test_deposit_role_is_forbidden(): void
    {
        $deposito = User::factory()->conSucursal($this->sucursal, Rol::Deposito)->create();

        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '12100')],
        );

        $this->actingAs($deposito)
            ->get(route('ventas.devoluciones.create', $venta))
            ->assertForbidden();

        $this->actingAs($deposito)
            ->post(route('ventas.devoluciones.store', $venta), [
                'cantidades' => [$venta->items->first()->id => 1],
                'motivo' => 'Intento sin permiso',
                'resolucion' => ResolucionDevolucion::SaldoAFavor->value,
            ])
            ->assertForbidden();

        $this->actingAs($deposito)
            ->get(route('ventas.devoluciones.index'))
            ->assertForbidden();
    }

    public function test_other_branch_returns_404(): void
    {
        $otraSucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Norte']);
        $otroPunto = PuntoVenta::factory()->create(['sucursal_id' => $otraSucursal->id, 'numero' => 2]);
        $otroVendedorUser = User::factory()->conSucursal($otraSucursal, Rol::Vendedor)->create();
        $otroVendedor = Vendedor::factory()->for($otroVendedorUser)->create();
        $otroCliente = Cliente::factory()->create();
        DB::transaction(fn () => app(CuentasCorrientes::class)->acreditar($otroCliente, '20000.00', 'Saldo inicial', sucursal: $otraSucursal));

        Stock::factory()->create(['variante_id' => $this->negro37->id, 'sucursal_id' => $otraSucursal->id, 'cantidad' => 5]);

        app(SucursalActiva::class)->establecer($otraSucursal, $otroVendedorUser);

        $otraVenta = app(Ventas::class)->registrar(
            $otraSucursal,
            $otroPunto,
            $otroVendedor,
            $otroCliente,
            [['variante_id' => $this->negro37->id, 'cantidad' => 1, 'descuento' => '0']],
            '0',
            [$this->pago($this->saldoAFavor, '12100') + ['plan_cuota_id' => null, 'referencia' => null, 'lote' => null, 'cupon' => null]],
        );

        $this->operarComo($this->vendedorUser);

        // Accessing return screen of another branch -> 404
        $this->actingAs($this->vendedorUser)
            ->get(route('ventas.devoluciones.create', $otraVenta))
            ->assertNotFound();

        $this->actingAs($this->vendedorUser)
            ->post(route('ventas.devoluciones.store', $otraVenta), [
                'cantidades' => [$otraVenta->items->first()->id => 1],
                'motivo' => 'Devolución otra sucursal',
                'resolucion' => ResolucionDevolucion::SaldoAFavor->value,
            ])
            ->assertNotFound();

        // Register a devolucion in the other branch
        $otraDevolucion = Devolucion::create([
            'sucursal_id' => $otraSucursal->id,
            'venta_id' => $otraVenta->id,
            'cliente_id' => null,
            'fecha' => now()->toDateString(),
            'motivo' => 'Otra',
            'resolucion' => ResolucionDevolucion::Reembolso,
            'importe' => '12100.00',
            'neto' => '10000.00',
            'iva' => '2100.00',
            'user_id' => $otroVendedorUser->id,
        ]);

        $this->actingAs($this->vendedorUser)
            ->get(route('ventas.devoluciones.show', $otraDevolucion))
            ->assertNotFound();
    }

    public function test_index_lists_active_branch_devoluciones_with_filters(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Marcos Gomez']);

        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '12100')],
            $cliente,
        );

        $devolucion = app(Devoluciones::class)->registrar(
            $venta,
            [$venta->items->first()->id => 1],
            'Talle chico',
            ResolucionDevolucion::SaldoAFavor,
        );

        $this->actingAs($this->vendedorUser)
            ->get(route('ventas.devoluciones.index'))
            ->assertOk()
            ->assertSee('Devolución N° '.$devolucion->id)
            ->assertSee('Marcos Gomez')
            ->assertSee('Talle chico');

        $this->actingAs($this->vendedorUser)
            ->get(route('ventas.devoluciones.index', ['desde' => now()->addDay()->toDateString()]))
            ->assertOk()
            ->assertDontSee('Devolución N° '.$devolucion->id)
            ->assertSee('No hay devoluciones para los filtros elegidos.');
    }

    public function test_detail_with_printable_credit_note_in_ticket_and_a4(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Sofia Lopez']);

        $venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            [$this->pago($this->efectivo, '12100')],
            $cliente,
        );

        $devolucion = app(Devoluciones::class)->registrar(
            $venta,
            [$venta->items->first()->id => 1],
            'Cambio de modelo',
            ResolucionDevolucion::SaldoAFavor,
        );

        $notaCredito = $devolucion->notaCredito;
        $this->assertNotNull($notaCredito);

        // Check ticket format
        $this->actingAs($this->vendedorUser)
            ->get(route('comprobantes.show', [$notaCredito, 'formato' => 'ticket']))
            ->assertOk()
            ->assertSee('Nota de crédito interna')
            ->assertSee(Comprobante::LEYENDA_INTERNO)
            ->assertSee('ticket')
            ->assertSee('Bota Ana');

        // Check A4 format
        $this->actingAs($this->vendedorUser)
            ->get(route('comprobantes.show', [$notaCredito, 'formato' => 'a4']))
            ->assertOk()
            ->assertSee('Nota de crédito interna')
            ->assertSee(Comprobante::LEYENDA_INTERNO)
            ->assertSee('a4')
            ->assertSee('Bota Ana');
    }
}
