<?php

namespace Tests\Feature\Ventas;

use App\Enums\EstadoVenta;
use App\Enums\TipoComprobante;
use App\Enums\TipoMovimientoStock;
use App\Models\Auditoria;
use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Models\CcMovimiento;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\MovimientoStock;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EscenarioVentas;
use Tests\TestCase;

class AnularVentaTest extends TestCase
{
    use EscenarioVentas, RefreshDatabase;

    private CajaSesion $sesion;

    private Cliente $cliente;

    private Venta $venta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararEscenarioVentas();
        $this->sesion = $this->abrirCaja($this->vendedorUser);
        $this->operarComo($this->vendedorUser);
        $this->cliente = $this->clienteConSaldoAFavor('8000');

        $this->venta = $this->vender(
            [['variante_id' => $this->negro37->id, 'cantidad' => 2], ['variante_id' => $this->media->id, 'cantidad' => 1]],
            [$this->pago($this->saldoAFavor, '5000'), $this->pago($this->efectivo, '30250')],
            $this->cliente,
        );
    }

    public function test_the_manager_voids_a_sale_reversing_stock_cash_and_customer_account(): void
    {
        $this->assertSame(3, $this->stockDe($this->negro37));
        $this->assertSame('3000.00', $this->cliente->cuentaCorriente->fresh()->saldoAFavor());

        $this->actingAs($this->gerente)
            ->post(route('ventas.anular', $this->venta), ['motivo' => 'Cliente se arrepintió'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('ventas.show', $this->venta));

        $venta = $this->venta->fresh();
        $this->assertSame(EstadoVenta::Anulada, $venta->estado);
        $this->assertSame($this->gerente->id, $venta->anulada_por);
        $this->assertNotNull($venta->anulada_at);
        $this->assertSame('Cliente se arrepintió', $venta->motivo_anulacion);
        $this->assertSame('35250.00', $venta->total);

        $this->assertSame(5, $this->stockDe($this->negro37));
        $this->assertSame(5, $this->stockDe($this->media));
        $ingresos = MovimientoStock::where('tipo', TipoMovimientoStock::Ingreso->value)->get();
        $this->assertCount(2, $ingresos);
        $this->assertTrue($ingresos->every(fn (MovimientoStock $movimiento) => $movimiento->reversa_de_id !== null && $movimiento->origen->is($venta)));

        $original = CajaMovimiento::whereNull('reversa_de_id')->sole();
        $contraasiento = CajaMovimiento::whereNotNull('reversa_de_id')->sole();
        $this->assertSame($original->id, $contraasiento->reversa_de_id);
        $this->assertSame('-30250.00', $contraasiento->importe);
        $this->assertSame('0.00', $this->sesion->fresh()->esperadoPorMedio()[$this->efectivo->id]);

        $this->assertSame('8000.00', $this->cliente->cuentaCorriente->fresh()->saldoAFavor());
        $this->assertSame(1, CcMovimiento::whereNotNull('reversa_de_id')->count());

        $anulacion = $venta->comprobanteAnulacion;
        $this->assertSame(TipoComprobante::AnulacionInterna, $anulacion->tipo);
        $this->assertSame(1, $anulacion->numero);
        $this->assertSame($venta->comprobante->id, $anulacion->comprobante_anulado_id);
        $this->assertSame('00001-00000001', $anulacion->datos_json['anula']['numero_completo']);
        $this->assertSame('Cliente se arrepintió', $anulacion->datos_json['motivo']);
        $this->assertSame('Documento no válido como factura', $anulacion->datos_json['leyenda']);

        $auditoria = Auditoria::where('accion', 'ventas.anulada')->sole();
        $this->assertSame($this->gerente->id, $auditoria->user_id);
        $this->assertSame('Cliente se arrepintió', $auditoria->despues['motivo']);
    }

    public function test_the_original_receipt_is_kept_and_the_void_receipt_prints_the_reference(): void
    {
        $original = $this->venta->comprobante;
        $datosOriginales = $original->datos_json;

        $this->actingAs($this->gerente)->post(route('ventas.anular', $this->venta), ['motivo' => 'Error de carga']);

        $this->assertSame($datosOriginales, $original->fresh()->datos_json);
        $this->assertSame(2, Comprobante::count());

        $this->actingAs($this->gerente)->get(route('comprobantes.show', $this->venta->fresh()->comprobanteAnulacion))
            ->assertOk()
            ->assertSee('Comprobante interno de anulación')
            ->assertSee('Anula el comprobante N° 00001-00000001')
            ->assertSee('Error de carga')
            ->assertSee('Documento no válido como factura');

        $this->actingAs($this->gerente)->get(route('ventas.show', $this->venta))
            ->assertOk()
            ->assertSee('Anulada')
            ->assertSee('Error de carga')
            ->assertDontSee('Anular venta</summary>', false);
    }

    public function test_a_sale_is_voided_only_once(): void
    {
        $this->actingAs($this->gerente)->post(route('ventas.anular', $this->venta), ['motivo' => 'Primera']);

        $this->actingAs($this->gerente)
            ->post(route('ventas.anular', $this->venta), ['motivo' => 'Segunda'])
            ->assertSessionHasErrorsIn('anulacion', ['motivo']);

        $this->assertSame(2, MovimientoStock::where('tipo', TipoMovimientoStock::Ingreso->value)->count());
        $this->assertSame(1, CajaMovimiento::whereNotNull('reversa_de_id')->count());
        $this->assertSame(1, Comprobante::where('tipo', TipoComprobante::AnulacionInterna->value)->count());
        $this->assertSame('Primera', $this->venta->fresh()->motivo_anulacion);
    }

    public function test_a_closed_cash_session_prevents_the_void_without_movements(): void
    {
        $this->sesion->forceFill(['estado' => 'cerrada', 'cierre' => now()])->save();

        $this->actingAs($this->gerente)
            ->post(route('ventas.anular', $this->venta), ['motivo' => 'Tarde'])
            ->assertSessionHasErrorsIn('anulacion', ['motivo' => 'La caja de la venta ya se cerró: no se puede anular. Registrá un cambio o devolución.']);

        $this->assertSame(EstadoVenta::Confirmada, $this->venta->fresh()->estado);
        $this->assertSame(3, $this->stockDe($this->negro37));
        $this->assertSame(0, CajaMovimiento::whereNotNull('reversa_de_id')->count());
        $this->assertSame(0, CcMovimiento::whereNotNull('reversa_de_id')->count());
        $this->assertSame(1, Comprobante::count());
    }

    public function test_the_reason_is_required(): void
    {
        $this->actingAs($this->gerente)
            ->post(route('ventas.anular', $this->venta), ['motivo' => ''])
            ->assertSessionHasErrorsIn('anulacion', ['motivo']);

        $this->assertSame(EstadoVenta::Confirmada, $this->venta->fresh()->estado);
    }

    public function test_sellers_and_cashiers_cannot_void(): void
    {
        foreach ([$this->vendedorUser, $this->cajero] as $user) {
            $this->actingAs($user)->post(route('ventas.anular', $this->venta), ['motivo' => 'No'])->assertForbidden();
        }

        $this->assertSame(EstadoVenta::Confirmada, $this->venta->fresh()->estado);
    }
}
