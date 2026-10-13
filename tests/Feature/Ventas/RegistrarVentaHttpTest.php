<?php

namespace Tests\Feature\Ventas;

use App\Enums\Rol;
use App\Http\Requests\Ventas\RegistrarVentaRequest;
use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Vendedor;
use App\Models\Venta;
use App\Models\VentaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EscenarioVentas;
use Tests\TestCase;

class RegistrarVentaHttpTest extends TestCase
{
    use EscenarioVentas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararEscenarioVentas();
        $this->abrirCaja($this->vendedorUser);
    }

    public function test_a_json_sale_returns_201_with_the_detail_url(): void
    {
        $respuesta = $this->actingAs($this->vendedorUser)->postJson(route('ventas.store'), $this->datos());

        $venta = Venta::sole();
        $respuesta->assertCreated()
            ->assertJson(['venta_id' => $venta->id, 'url' => route('ventas.show', $venta)]);
        $this->assertSame($this->puntoVenta->id, $venta->punto_venta_id);
        $this->assertSame(4, $this->stockDe($this->negro37));
    }

    public function test_a_form_sale_redirects_to_the_detail(): void
    {
        $this->actingAs($this->vendedorUser)->post(route('ventas.store'), $this->datos())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('ventas.show', Venta::sole()));
    }

    public function test_business_errors_come_back_as_422_on_the_right_field(): void
    {
        $this->actingAs($this->vendedorUser)
            ->postJson(route('ventas.store'), $this->datos(['pagos' => [['medio_pago_id' => $this->efectivo->id, 'importe' => '100']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pagos']);

        $this->actingAs($this->vendedorUser)
            ->postJson(route('ventas.store'), $this->datos(['items' => [['variante_id' => $this->media->id, 'cantidad' => 6]], 'pagos' => [['medio_pago_id' => $this->efectivo->id, 'importe' => '66300']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.cantidad']);

        $this->assertSame(0, Venta::count());
    }

    public function test_a_closed_cash_register_is_reported_on_the_payments(): void
    {
        $this->actingAs($this->cajero)
            ->postJson(route('ventas.store'), $this->datos())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pagos' => 'No tenés una caja abierta']);
    }

    public function test_a_discount_without_authorization_is_rejected(): void
    {
        $this->actingAs($this->vendedorUser)
            ->postJson(route('ventas.store'), $this->datos(['descuento' => '100', 'pagos' => [['medio_pago_id' => $this->efectivo->id, 'importe' => '12000']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['autorizacion']);

        $this->assertSame(0, Venta::count());
    }

    public function test_the_manager_authorizes_a_discount_with_email_and_password(): void
    {
        $this->actingAs($this->vendedorUser)
            ->postJson(route('ventas.store'), $this->datos([
                'items' => [['variante_id' => $this->negro37->id, 'cantidad' => 1, 'descuento' => '100']],
                'pagos' => [['medio_pago_id' => $this->efectivo->id, 'importe' => '12000']],
                'autorizacion' => ['email' => 'gerente@example.com', 'password' => 'password'],
            ]))
            ->assertCreated();

        $this->assertSame($this->gerente->id, VentaItem::sole()->descuento_autorizado_por);
        $this->assertTrue(Auditoria::where('accion', 'ventas.descuento_autorizado')->where('user_id', $this->vendedorUser->id)->exists());
    }

    public function test_a_wrong_password_or_an_authorizer_without_permission_is_rejected(): void
    {
        $con = fn (array $autorizacion) => $this->datos([
            'descuento' => '100',
            'pagos' => [['medio_pago_id' => $this->efectivo->id, 'importe' => '12000']],
            'autorizacion' => $autorizacion,
        ]);

        $this->actingAs($this->vendedorUser)->postJson(route('ventas.store'), $con(['email' => 'gerente@example.com', 'password' => 'otra']))
            ->assertJsonValidationErrors(['autorizacion.email']);

        $this->actingAs($this->vendedorUser)->postJson(route('ventas.store'), $con(['email' => $this->cajero->email, 'password' => 'password']))
            ->assertJsonValidationErrors(['autorizacion.email']);

        $otraSucursal = User::factory()->conSucursal(Sucursal::factory()->create(), Rol::Gerente)->create();
        $this->actingAs($this->vendedorUser)->postJson(route('ventas.store'), $con(['email' => $otraSucursal->email, 'password' => 'password']))
            ->assertJsonValidationErrors(['autorizacion.email']);

        $this->gerente->update(['activo' => false]);
        $this->actingAs($this->vendedorUser)->postJson(route('ventas.store'), $con(['email' => 'gerente@example.com', 'password' => 'password']))
            ->assertJsonValidationErrors(['autorizacion.email']);

        $this->assertSame(0, Venta::count());
    }

    public function test_failed_authorizations_are_rate_limited(): void
    {
        $datos = $this->datos([
            'descuento' => '100',
            'pagos' => [['medio_pago_id' => $this->efectivo->id, 'importe' => '12000']],
            'autorizacion' => ['email' => 'gerente@example.com', 'password' => 'otra'],
        ]);

        for ($intento = 0; $intento < RegistrarVentaRequest::INTENTOS_AUTORIZACION; $intento++) {
            $this->actingAs($this->vendedorUser)->postJson(route('ventas.store'), $datos)->assertUnprocessable();
        }

        $datos['autorizacion']['password'] = 'password';
        $this->actingAs($this->vendedorUser)->postJson(route('ventas.store'), $datos)
            ->assertJsonValidationErrors(['autorizacion.email' => 'Demasiados intentos']);
    }

    public function test_a_manager_selling_authorizes_the_discount_without_password(): void
    {
        $this->abrirCaja($this->gerente);

        $this->actingAs($this->gerente)
            ->postJson(route('ventas.store'), $this->datos(['descuento' => '100', 'pagos' => [['medio_pago_id' => $this->efectivo->id, 'importe' => '12000']]]))
            ->assertCreated();

        $this->assertSame($this->gerente->id, Venta::sole()->descuento_autorizado_por);
    }

    public function test_the_seller_must_be_available_in_the_branch(): void
    {
        $ajeno = Vendedor::factory()->for(User::factory()->conSucursal(Sucursal::factory()->create(), Rol::Vendedor))->create();
        $inactivo = Vendedor::factory()->inactivo()->for(User::factory()->conSucursal($this->sucursal, Rol::Vendedor))->create();

        foreach ([$ajeno, $inactivo] as $vendedor) {
            $this->actingAs($this->vendedorUser)
                ->postJson(route('ventas.store'), $this->datos(['vendedor_id' => $vendedor->id]))
                ->assertJsonValidationErrors(['vendedor_id']);
        }
    }

    public function test_the_customer_must_be_active(): void
    {
        $inactivo = Cliente::factory()->inactivo()->create();

        $this->actingAs($this->vendedorUser)
            ->postJson(route('ventas.store'), $this->datos(['cliente_id' => $inactivo->id]))
            ->assertJsonValidationErrors(['cliente_id']);
    }

    public function test_the_point_of_sale_defaults_to_the_first_active_one_and_must_exist(): void
    {
        $this->puntoVenta->update(['activo' => false]);

        $this->actingAs($this->vendedorUser)->postJson(route('ventas.store'), $this->datos())
            ->assertJsonValidationErrors(['punto_venta_id' => 'no tiene un punto de venta activo']);

        $otro = PuntoVenta::factory()->create(['sucursal_id' => $this->sucursal->id, 'numero' => 7]);
        $ajeno = PuntoVenta::factory()->create(['numero' => 8]);

        $this->actingAs($this->vendedorUser)->postJson(route('ventas.store'), $this->datos(['punto_venta_id' => $ajeno->id]))
            ->assertJsonValidationErrors(['punto_venta_id']);

        $this->actingAs($this->vendedorUser)->postJson(route('ventas.store'), $this->datos())->assertCreated();
        $this->assertSame($otro->id, Venta::sole()->punto_venta_id);
    }

    public function test_prices_sent_by_the_browser_are_ignored(): void
    {
        $datos = $this->datos();
        $datos['items'][0]['precio_final'] = '1.00';

        $this->actingAs($this->vendedorUser)->postJson(route('ventas.store'), $datos)
            ->assertJsonValidationErrors(['items.0']);
    }

    public function test_the_preview_returns_totals_surcharge_and_pending_without_recording(): void
    {
        $this->actingAs($this->vendedorUser)
            ->postJson(route('ventas.previsualizar'), [
                'items' => [['variante_id' => $this->negro37->id, 'cantidad' => 1], ['variante_id' => $this->media->id, 'cantidad' => 1]],
                'descuento' => '0',
                'pagos' => [['medio_pago_id' => $this->credito->id, 'plan_cuota_id' => $this->tresCuotas->id, 'importe' => '20000']],
            ])
            ->assertOk()
            ->assertJson([
                'a_pagar' => '23150.00',
                'pagado' => '20000.00',
                'pendiente' => '3150.00',
                'recargo' => '2000.00',
                'total' => '25150.00',
                'pagos' => [['recargo' => '2000.00', 'cobrado' => '22000.00']],
                'requiere_autorizacion' => false,
            ]);

        $this->actingAs($this->vendedorUser)
            ->postJson(route('ventas.previsualizar'), ['items' => [['variante_id' => $this->negro37->id, 'cantidad' => 1, 'descuento' => '50']]])
            ->assertOk()
            ->assertJson(['total' => '12050.00', 'pendiente' => '12050.00', 'requiere_autorizacion' => true]);

        $this->actingAs($this->vendedorUser)
            ->postJson(route('ventas.previsualizar'), ['items' => [['variante_id' => $this->negro37->id, 'cantidad' => 1, 'descuento' => '99999']]])
            ->assertJsonValidationErrors(['items.0.descuento']);

        $this->assertSame(0, Venta::count());
    }

    public function test_warehouse_users_cannot_sell(): void
    {
        $deposito = User::factory()->conSucursal($this->sucursal, Rol::Deposito)->create();

        $this->actingAs($deposito)->postJson(route('ventas.store'), $this->datos())->assertForbidden();
        $this->actingAs($deposito)->postJson(route('ventas.previsualizar'), $this->datos())->assertForbidden();
        $this->actingAs($deposito)->get(route('ventas.create'))->assertForbidden();
        $this->actingAs($deposito)->get(route('ventas.index'))->assertForbidden();
        $this->actingAs($deposito)->getJson(route('ventas.variantes.buscar', ['q' => 'Bota']))->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return array_replace([
            'vendedor_id' => $this->vendedor->id,
            'cliente_id' => null,
            'items' => [['variante_id' => $this->negro37->id, 'cantidad' => 1]],
            'descuento' => '0',
            'pagos' => [['medio_pago_id' => $this->efectivo->id, 'importe' => '12100']],
        ], $cambios);
    }
}
