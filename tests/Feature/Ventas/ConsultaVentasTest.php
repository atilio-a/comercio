<?php

namespace Tests\Feature\Ventas;

use App\Enums\Rol;
use App\Models\Caja;
use App\Models\CajaSesion;
use App\Models\Cliente;
use App\Models\PuntoVenta;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Vendedor;
use App\Models\Venta;
use App\Services\SucursalActiva;
use App\Services\Ventas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EscenarioVentas;
use Tests\TestCase;

class ConsultaVentasTest extends TestCase
{
    use EscenarioVentas, RefreshDatabase;

    private User $otroVendedorUser;

    private Vendedor $otroVendedor;

    private Cliente $cliente;

    private Venta $propia;

    private Venta $ajena;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararEscenarioVentas();
        $this->otroVendedorUser = User::factory()->conSucursal($this->sucursal, Rol::Vendedor)->create(['name' => 'Omar Otro']);
        $this->otroVendedor = Vendedor::factory()->for($this->otroVendedorUser)->create();
        $this->cliente = Cliente::factory()->create(['nombre' => 'María López']);

        $this->abrirCaja($this->vendedorUser);
        $this->abrirCaja($this->otroVendedorUser);

        $this->operarComo($this->vendedorUser);
        $this->propia = $this->vender([['variante_id' => $this->negro37->id, 'cantidad' => 1]], [$this->pago($this->efectivo, '12100')], $this->cliente);

        $this->operarComo($this->otroVendedorUser);
        $this->ajena = $this->vender([['variante_id' => $this->media->id, 'cantidad' => 2]], [$this->pago($this->efectivo, '22100')], $this->cliente, vendedor: $this->otroVendedor);
    }

    public function test_a_seller_only_sees_their_own_sales(): void
    {
        $this->actingAs($this->vendedorUser)->get(route('ventas.index'))
            ->assertOk()
            ->assertSee('Mis ventas')
            ->assertSee('Venta N° '.$this->propia->id)
            ->assertDontSee('Venta N° '.$this->ajena->id)
            ->assertDontSee('id="vendedor_id"', false);

        $this->actingAs($this->vendedorUser)->get(route('ventas.show', $this->propia))->assertOk()->assertSee('Bota Ana');
        $this->actingAs($this->vendedorUser)->get(route('ventas.show', $this->ajena))->assertForbidden();
    }

    public function test_a_seller_sees_a_sale_they_registered_for_another_seller(): void
    {
        $this->operarComo($this->vendedorUser);
        $registrada = $this->vender([['variante_id' => $this->negro38->id, 'cantidad' => 1]], [$this->pago($this->efectivo, '12100')], vendedor: $this->otroVendedor);

        $this->actingAs($this->vendedorUser)->get(route('ventas.show', $registrada))->assertOk();
    }

    public function test_the_cashier_sees_every_sale_and_filters_by_seller_and_status(): void
    {
        $this->actingAs($this->cajero)->get(route('ventas.index'))
            ->assertOk()
            ->assertSee('Venta N° '.$this->propia->id)
            ->assertSee('Venta N° '.$this->ajena->id);

        $this->actingAs($this->cajero)->get(route('ventas.index', ['vendedor_id' => $this->otroVendedor->id]))
            ->assertOk()
            ->assertDontSee('Venta N° '.$this->propia->id)
            ->assertSee('Venta N° '.$this->ajena->id);

        $this->operarComo($this->gerente);
        app(Ventas::class)->anular($this->ajena, 'Error de carga');

        $this->actingAs($this->cajero)->get(route('ventas.index', ['vendedor_id' => $this->otroVendedor->id, 'estado' => 'anulada']))
            ->assertOk()
            ->assertSee('Venta N° '.$this->ajena->id);
        $this->actingAs($this->cajero)->get(route('ventas.index', ['estado' => 'anulada']))
            ->assertOk()
            ->assertDontSee('Venta N° '.$this->propia->id);
        $this->actingAs($this->cajero)->get(route('ventas.index', ['desde' => now()->addDay()->toDateString()]))
            ->assertOk()
            ->assertSee('No hay ventas para los filtros elegidos.');
    }

    public function test_a_seller_cannot_filter_other_sellers_sales(): void
    {
        $this->actingAs($this->vendedorUser)->get(route('ventas.index', ['vendedor_id' => $this->otroVendedor->id]))
            ->assertOk()
            ->assertSee('Venta N° '.$this->propia->id)
            ->assertDontSee('Venta N° '.$this->ajena->id);
    }

    public function test_sales_of_another_branch_do_not_exist(): void
    {
        $otra = Sucursal::factory()->create();
        $this->cajero->sucursales()->attach($otra);
        $this->cajero->asignarRol(Rol::Cajero, $otra);
        $vendedorOtra = Vendedor::factory()->for(User::factory()->conSucursal($otra, Rol::Vendedor))->create();
        $puntoOtra = PuntoVenta::factory()->create(['sucursal_id' => $otra->id, 'numero' => 2]);
        $this->abrirCajaEn($otra, $this->cajero);
        Stock::factory()->create(['variante_id' => $this->negro37->id, 'sucursal_id' => $otra->id, 'cantidad' => 3]);

        $this->actingAs($this->cajero);
        app(SucursalActiva::class)->establecer($otra, $this->cajero);
        $ventaOtra = app(Ventas::class)->registrar($otra, $puntoOtra, $vendedorOtra, null, [['variante_id' => $this->negro37->id, 'cantidad' => 1, 'descuento' => '0']], '0', [['medio_pago_id' => $this->efectivo->id, 'plan_cuota_id' => null, 'importe' => '12100']]);

        $this->actingAs($this->cajero)->get(route('ventas.show', $ventaOtra))->assertNotFound();
        $this->actingAs($this->cajero)->get(route('comprobantes.show', $ventaOtra->comprobante))->assertNotFound();
        $this->actingAs($this->cajero)->get(route('ventas.index'))->assertOk()->assertDontSee('Venta N° '.$ventaOtra->id);
    }

    public function test_the_detail_shows_items_payments_and_receipt_links(): void
    {
        $this->actingAs($this->cajero)->get(route('ventas.show', $this->propia))
            ->assertOk()
            ->assertSee('Bota Ana')
            ->assertSee('Negro / 37')
            ->assertSee('María López')
            ->assertSee('Valeria Vendedora')
            ->assertSee('12.100,00')
            ->assertSee('00001-00000001')
            ->assertSee(route('comprobantes.show', [$this->propia->comprobante, 'formato' => 'ticket']), false)
            ->assertSee(route('comprobantes.show', [$this->propia->comprobante, 'formato' => 'a4']), false)
            ->assertDontSee('Anular venta');

        $this->actingAs($this->gerente)->get(route('ventas.show', $this->propia))->assertOk()->assertSee('Anular venta');
    }

    public function test_the_customer_profile_lists_their_purchases_with_the_same_visibility(): void
    {
        $this->actingAs($this->cajero)->get(route('clientes.show', $this->cliente))
            ->assertOk()
            ->assertSee('Historial de compras')
            ->assertSee('Venta N° '.$this->propia->id)
            ->assertSee('Venta N° '.$this->ajena->id)
            ->assertSee('00001-00000002');

        $this->actingAs($this->vendedorUser)->get(route('clientes.show', $this->cliente))
            ->assertOk()
            ->assertSee('Venta N° '.$this->propia->id)
            ->assertDontSee('Venta N° '.$this->ajena->id);
    }

    public function test_the_receipt_prints_as_ticket_and_a4_with_the_legend(): void
    {
        $comprobante = $this->propia->comprobante;

        $this->actingAs($this->vendedorUser)->get(route('comprobantes.show', $comprobante))
            ->assertOk()
            ->assertSee('Documento no válido como factura')
            ->assertSee('00001-00000001')
            ->assertSee('Bota Ana Negro / 37')
            ->assertSee('María López')
            ->assertSee('Efectivo')
            ->assertSee('12.100,00')
            ->assertSee('class="hoja ticket"', false)
            ->assertSee('size: 80mm auto', false)
            ->assertSee('Imprimir o guardar PDF');

        $this->actingAs($this->vendedorUser)->get(route('comprobantes.show', [$comprobante, 'formato' => 'a4']))
            ->assertOk()
            ->assertSee('class="hoja a4"', false)
            ->assertSee('size: A4', false)
            ->assertSee('Documento no válido como factura');
    }

    public function test_reprinting_uses_the_data_of_the_moment_it_was_issued(): void
    {
        $this->cliente->update(['nombre' => 'María López de García']);
        $this->negro37->producto->update(['nombre' => 'Bota Ana II']);

        $this->actingAs($this->vendedorUser)->get(route('comprobantes.show', $this->propia->comprobante))
            ->assertOk()
            ->assertSee('María López')
            ->assertDontSee('María López de García')
            ->assertDontSee('Bota Ana II');
    }

    public function test_a_seller_cannot_print_another_sellers_receipt(): void
    {
        $this->actingAs($this->vendedorUser)->get(route('comprobantes.show', $this->ajena->comprobante))->assertForbidden();
        $this->actingAs($this->cajero)->get(route('comprobantes.show', $this->ajena->comprobante))->assertOk();
    }

    private function abrirCajaEn(Sucursal $sucursal, User $user): void
    {
        $caja = Caja::factory()->create(['sucursal_id' => $sucursal->id]);
        CajaSesion::factory()->for($caja)->for($user)->create();
    }
}
