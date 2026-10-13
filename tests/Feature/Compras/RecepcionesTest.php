<?php

namespace Tests\Feature\Compras;

use App\Enums\EstadoOrdenCompra;
use App\Enums\Parametro;
use App\Enums\Rol;
use App\Enums\TipoMovimientoStock;
use App\Models\Auditoria;
use App\Models\Color;
use App\Models\MovimientoStock;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Recepcion;
use App\Models\RecepcionItem;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Variante;
use App\Services\MovimientosStock;
use App\Services\Parametros;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class RecepcionesTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $gerente;

    private User $deposito;

    private Proveedor $proveedor;

    private Variante $negro37;

    private Variante $negro38;

    private OrdenCompra $orden;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->sucursal = Sucursal::factory()->create(['nombre' => Sucursal::PRINCIPAL]);
        $this->gerente = User::factory()->conSucursal($this->sucursal, Rol::Gerente)->create();
        $this->deposito = User::factory()->conSucursal($this->sucursal, Rol::Deposito)->create(['name' => 'Dani Depósito']);
        $this->proveedor = Proveedor::factory()->create(['razon_social' => 'Calzados del Norte SA']);
        $producto = Producto::factory()->create(['nombre' => 'Bota Ana']);
        $negro = Color::factory()->create(['nombre' => 'Negro']);
        $this->negro37 = Variante::factory()->for($producto)->for($negro)->create(['talle' => '37', 'codigo_barras' => '7790000000037', 'costo' => '1000.00']);
        $this->negro38 = Variante::factory()->for($producto)->for($negro)->create(['talle' => '38', 'codigo_barras' => '7790000000038', 'costo' => '1000.00']);

        $this->orden = OrdenCompra::factory()->emitida()->create([
            'sucursal_id' => $this->sucursal->id,
            'proveedor_id' => $this->proveedor->id,
            'user_id' => $this->gerente->id,
        ]);
        OrdenCompraItem::factory()->for($this->orden, 'orden')->create(['variante_id' => $this->negro37->id, 'cantidad_pedida' => 10, 'costo' => '1200.00']);
        OrdenCompraItem::factory()->for($this->orden, 'orden')->create(['variante_id' => $this->negro38->id, 'cantidad_pedida' => 5, 'costo' => '1200.00']);
    }

    public function test_partial_reception_of_an_order(): void
    {
        $this->actingAs($this->deposito)->get(route('compras.recepciones.create', ['orden' => $this->orden->id]))
            ->assertOk()
            ->assertSee('Recibir orden N° '.$this->orden->numero)
            ->assertSee('Recibir todo lo pendiente');

        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), $this->contraOrden([4, 0]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('compras.recepciones.show', Recepcion::sole()));

        $recepcion = Recepcion::sole();
        $this->assertSame($this->orden->id, $recepcion->orden_compra_id);
        $this->assertSame($this->proveedor->id, $recepcion->proveedor_id);
        $this->assertSame('R 0001-00001234', $recepcion->referencia);
        $this->assertSame($this->deposito->id, $recepcion->user_id);
        $this->assertNotNull($recepcion->dispositivo);
        $this->assertSame(1, $recepcion->items()->count());

        $this->orden->refresh();
        $this->assertSame(EstadoOrdenCompra::Parcial, $this->orden->estado);
        $this->assertSame([4, 0], $this->orden->items()->orderBy('id')->pluck('cantidad_recibida')->all());
        $this->assertSame(4, $this->cantidad($this->negro37));
        $this->assertSame(0, $this->cantidad($this->negro38));

        $this->actingAs($this->deposito)->get(route('compras.recepciones.show', $recepcion))
            ->assertOk()
            ->assertSee('Recepción N° '.$recepcion->id)
            ->assertSee('Negro / 37')
            ->assertDontSee('Anular recepción');
    }

    public function test_full_reception_in_two_parts_marks_the_order_received(): void
    {
        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), $this->contraOrden([4, 5]))->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrdenCompra::Parcial, $this->orden->refresh()->estado);

        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), $this->contraOrden([6]))->assertSessionHasNoErrors();

        $this->assertSame(EstadoOrdenCompra::Recibida, $this->orden->refresh()->estado);
        $this->assertSame([10, 5], $this->orden->items()->orderBy('id')->pluck('cantidad_recibida')->all());
        $this->assertSame(10, $this->cantidad($this->negro37));
        $this->assertSame(5, $this->cantidad($this->negro38));

        $this->actingAs($this->deposito)->get(route('compras.recepciones.create', ['orden' => $this->orden->id]))
            ->assertRedirect(route('compras.ordenes.show', $this->orden));
        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), $this->contraOrden([1]))
            ->assertSessionHasErrors('orden_compra_id');
    }

    public function test_each_item_creates_a_stock_entry_with_the_reception_as_origin(): void
    {
        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), $this->contraOrden([4, 2]))->assertSessionHasNoErrors();

        $recepcion = Recepcion::sole();
        $movimientos = MovimientoStock::query()->orderBy('id')->get();

        $this->assertSame([$this->negro37->id, $this->negro38->id], $movimientos->pluck('variante_id')->all());
        foreach ($movimientos as $movimiento) {
            $this->assertSame(TipoMovimientoStock::Ingreso, $movimiento->tipo);
            $this->assertTrue($movimiento->origen->is($recepcion));
            $this->assertSame($this->deposito->id, $movimiento->user_id);
            $this->assertSame($recepcion->dispositivo, $movimiento->dispositivo);
            $this->assertSame($recepcion->fecha->toDateString(), $movimiento->fecha->toDateString());
            $this->assertSame('Recepción N° '.$recepcion->id, $movimiento->motivo);
        }
        $this->assertSame([4, 2], $movimientos->pluck('cantidad')->all());
    }

    public function test_receiving_more_than_pending_is_rejected_without_touching_stock(): void
    {
        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), $this->contraOrden([4]))->assertSessionHasNoErrors();

        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), $this->contraOrden([7, 1]))
            ->assertSessionHasErrors(['items.0.cantidad' => 'Supera lo pendiente de la orden (6). Registrá el excedente como recepción sin orden.']);

        $this->assertSame(1, Recepcion::count());
        $this->assertSame(4, $this->cantidad($this->negro37));
        $this->assertSame(0, $this->cantidad($this->negro38));
        $this->assertSame([4, 0], $this->orden->items()->orderBy('id')->pluck('cantidad_recibida')->all());
    }

    public function test_variant_outside_the_order_is_rejected(): void
    {
        $otra = Variante::factory()->create();
        $datos = $this->contraOrden([1]);
        $datos['items'][] = ['variante_id' => $otra->id, 'cantidad' => '2', 'costo' => '100'];

        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), $datos)
            ->assertSessionHasErrors('items.1.variante_id');

        $this->assertSame(0, Recepcion::count());
        $this->assertSame(0, MovimientoStock::count());
    }

    public function test_draft_or_cancelled_orders_cannot_be_received(): void
    {
        foreach ([EstadoOrdenCompra::Borrador, EstadoOrdenCompra::Anulada] as $estado) {
            $this->orden->update(['estado' => $estado]);

            $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), $this->contraOrden([1]))
                ->assertSessionHasErrors('orden_compra_id');
        }

        $this->assertSame(0, Recepcion::count());
    }

    public function test_at_least_one_quantity_is_required(): void
    {
        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), $this->contraOrden([0, 0]))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Recepcion::count());
    }

    public function test_free_reception_without_order(): void
    {
        $this->actingAs($this->deposito)->get(route('compras.recepciones.create'))
            ->assertOk()
            ->assertSee('Recepción sin orden')
            ->assertSee($this->proveedor->razon_social);

        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->toDateString(),
            'items' => [
                ['variante_id' => $this->negro38->id, 'cantidad' => '3', 'costo' => '1100'],
                ['variante_id' => $this->negro37->id, 'cantidad' => '2', 'costo' => '1100'],
            ],
        ])->assertSessionHasNoErrors();

        $recepcion = Recepcion::sole();
        $this->assertNull($recepcion->orden_compra_id);
        $this->assertSame(2, $recepcion->items()->count());
        $this->assertSame(2, $this->cantidad($this->negro37));
        $this->assertSame(3, $this->cantidad($this->negro38));
        // Locks follow ascending variant id regardless of the form order.
        $this->assertSame([$this->negro37->id, $this->negro38->id], MovimientoStock::orderBy('id')->pluck('variante_id')->all());
        $this->assertSame(EstadoOrdenCompra::Emitida, $this->orden->refresh()->estado);
    }

    public function test_free_reception_requires_an_active_supplier_and_active_variants(): void
    {
        $inactivo = Proveedor::factory()->inactivo()->create();
        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), [
            'proveedor_id' => $inactivo->id,
            'fecha' => now()->toDateString(),
            'items' => [['variante_id' => $this->negro37->id, 'cantidad' => '1', 'costo' => '1']],
        ])->assertSessionHasErrors('proveedor_id');

        $this->negro37->update(['activo' => false]);
        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->toDateString(),
            'items' => [['variante_id' => $this->negro37->id, 'cantidad' => '1', 'costo' => '1']],
        ])->assertSessionHasErrors('items.0.variante_id');

        $this->assertSame(0, Recepcion::count());
    }

    public function test_cost_update_is_applied_and_audited_only_when_requested(): void
    {
        $datos = $this->contraOrden([2, 1]);
        $datos['items'][0]['costo'] = '1250.5';
        $datos['items'][0]['actualizar_costo'] = '1';
        $datos['items'][1]['costo'] = '1250.5';

        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), $datos)->assertSessionHasNoErrors();

        $recepcion = Recepcion::sole();
        $this->assertSame('1250.50', $this->negro37->refresh()->costo);
        $this->assertSame('1000.00', $this->negro38->refresh()->costo);
        $this->assertSame([true, false], $recepcion->items()->orderBy('variante_id')->pluck('actualiza_costo')->all());

        $auditoria = Auditoria::where('accion', 'variante.costo_actualizado')->sole();
        $this->assertSame($this->negro37->id, $auditoria->modelo_id);
        $this->assertSame(['costo' => '1000.00'], $auditoria->antes);
        $this->assertSame(['costo' => '1250.50', 'recepcion_id' => $recepcion->id], $auditoria->despues);
    }

    public function test_cancelling_a_reception_reverts_stock_and_order(): void
    {
        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), $this->contraOrden([4]))->assertSessionHasNoErrors();
        $recepcion = Recepcion::sole();
        $ingreso = MovimientoStock::sole();

        $this->actingAs($this->gerente)->get(route('compras.recepciones.show', $recepcion))->assertOk()->assertSee('Anular recepción');
        $this->actingAs($this->gerente)->post(route('compras.recepciones.anular', $recepcion), ['motivo' => 'Remito duplicado'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('compras.recepciones.show', $recepcion));

        $egreso = MovimientoStock::where('reversa_de_id', $ingreso->id)->sole();
        $this->assertSame(TipoMovimientoStock::Egreso, $egreso->tipo);
        $this->assertSame(-4, $egreso->cantidad);
        $this->assertTrue($egreso->origen->is($recepcion));
        $this->assertStringContainsString('Remito duplicado', $egreso->motivo);
        $this->assertSame(0, $this->cantidad($this->negro37));

        $this->orden->refresh();
        $this->assertSame(EstadoOrdenCompra::Emitida, $this->orden->estado);
        $this->assertSame([0, 0], $this->orden->items()->orderBy('id')->pluck('cantidad_recibida')->all());

        $recepcion->refresh();
        $this->assertTrue($recepcion->estaAnulada());
        $this->assertSame($this->gerente->id, $recepcion->anulada_por_id);
        $this->assertSame('Remito duplicado', $recepcion->motivo_anulacion);
        $this->assertSame('Remito duplicado', Auditoria::where('accion', 'compras.recepcion.anulada')->sole()->despues['motivo']);

        $this->actingAs($this->gerente)->get(route('compras.recepciones.show', $recepcion))
            ->assertOk()
            ->assertSee('Anulada')
            ->assertDontSee('Motivo de la anulación');

        // Once the reception is reverted, the order can be cancelled again.
        $this->actingAs($this->gerente)->post(route('compras.ordenes.anular', $this->orden), ['motivo' => 'Ya no se necesita'])->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrdenCompra::Anulada, $this->orden->refresh()->estado);
    }

    public function test_cancelling_one_of_two_receptions_leaves_the_order_partial(): void
    {
        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), $this->contraOrden([4, 5]))->assertSessionHasNoErrors();
        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), $this->contraOrden([6]))->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrdenCompra::Recibida, $this->orden->refresh()->estado);

        $segunda = Recepcion::orderByDesc('id')->first();
        $this->actingAs($this->gerente)->post(route('compras.recepciones.anular', $segunda), ['motivo' => 'Error de carga'])->assertSessionHasNoErrors();

        $this->assertSame(EstadoOrdenCompra::Parcial, $this->orden->refresh()->estado);
        $this->assertSame([4, 5], $this->orden->items()->orderBy('id')->pluck('cantidad_recibida')->all());
    }

    public function test_cancellation_is_rejected_when_stock_would_go_negative(): void
    {
        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), $this->contraOrden([4, 2]))->assertSessionHasNoErrors();
        $recepcion = Recepcion::sole();
        $this->vender($this->negro38, 1);

        $this->actingAs($this->gerente)->post(route('compras.recepciones.anular', $recepcion), ['motivo' => 'Error'])
            ->assertSessionHasErrorsIn('anulacion', 'motivo');

        $this->assertStringContainsString('hay 1 disponibles', session('errors')->getBag('anulacion')->first('motivo'));
        $this->assertFalse($recepcion->refresh()->estaAnulada());
        $this->assertSame(4, $this->cantidad($this->negro37));
        $this->assertSame(1, $this->cantidad($this->negro38));
        $this->assertSame(0, MovimientoStock::whereNotNull('reversa_de_id')->count());
        $this->assertSame([4, 2], $this->orden->items()->orderBy('id')->pluck('cantidad_recibida')->all());
        $this->assertSame(0, Auditoria::where('accion', 'compras.recepcion.anulada')->count());
    }

    public function test_cancellation_with_negative_stock_enabled_is_recorded_and_audited(): void
    {
        app(Parametros::class)->guardar(Parametro::StockNegativo, true);
        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), $this->contraOrden([2]))->assertSessionHasNoErrors();
        $recepcion = Recepcion::sole();
        $this->vender($this->negro37, 2);

        $this->actingAs($this->gerente)->post(route('compras.recepciones.anular', $recepcion), ['motivo' => 'Error'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($recepcion->refresh()->estaAnulada());
        $this->assertSame(-2, $this->cantidad($this->negro37));
        $this->assertSame(1, Auditoria::where('accion', 'stock.negativo')->count());
    }

    public function test_a_reception_is_cancelled_only_once(): void
    {
        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), $this->contraOrden([4]))->assertSessionHasNoErrors();
        $recepcion = Recepcion::sole();
        $this->actingAs($this->gerente)->post(route('compras.recepciones.anular', $recepcion), ['motivo' => 'Error'])->assertSessionHasNoErrors();

        $this->actingAs($this->gerente)->post(route('compras.recepciones.anular', $recepcion), ['motivo' => 'Otra vez'])
            ->assertSessionHasErrorsIn('anulacion', 'motivo');

        $this->assertSame(1, MovimientoStock::whereNotNull('reversa_de_id')->count());
        $this->assertSame(0, $this->cantidad($this->negro37));
    }

    public function test_receptions_are_immutable_except_for_their_cancellation(): void
    {
        $recepcion = Recepcion::factory()->create(['sucursal_id' => $this->sucursal->id]);
        $item = RecepcionItem::factory()->for($recepcion)->create();

        $this->assertThrows(fn () => $recepcion->update(['referencia' => 'Otra']), LogicException::class);
        $this->assertThrows(fn () => $recepcion->delete(), LogicException::class);
        $this->assertThrows(fn () => $item->update(['cantidad' => 5]), LogicException::class);
        $this->assertThrows(fn () => $item->delete(), LogicException::class);

        $recepcion = $recepcion->fresh();
        $recepcion->forceFill(['anulada_at' => now(), 'motivo_anulacion' => 'Error'])->save();
        $this->assertThrows(fn () => $recepcion->forceFill(['motivo_anulacion' => 'Cambio'])->save(), LogicException::class);
    }

    public function test_permissions_by_role(): void
    {
        $this->actingAs($this->deposito)->post(route('compras.recepciones.store'), $this->contraOrden([1]))->assertSessionHasNoErrors();
        $recepcion = Recepcion::sole();

        $this->actingAs($this->deposito)->get(route('compras.recepciones.index'))->assertOk()->assertSee('Recepción N° '.$recepcion->id);
        $this->actingAs($this->deposito)->post(route('compras.recepciones.anular', $recepcion), ['motivo' => 'x'])->assertForbidden();

        $cajero = User::factory()->conSucursal($this->sucursal, Rol::Cajero)->create();
        $this->actingAs($cajero)->get(route('compras.recepciones.index'))->assertForbidden();
        $this->actingAs($cajero)->get(route('compras.recepciones.show', $recepcion))->assertForbidden();
        $this->actingAs($cajero)->get(route('compras.recepciones.create'))->assertForbidden();
        $this->actingAs($cajero)->post(route('compras.recepciones.store'), $this->contraOrden([1]))->assertForbidden();

        $this->assertSame(1, Recepcion::count());
        $this->assertFalse($recepcion->refresh()->estaAnulada());
    }

    public function test_orders_and_receptions_of_another_branch_do_not_exist(): void
    {
        $otra = Sucursal::factory()->create();
        $ordenAjena = OrdenCompra::factory()->emitida()->create(['sucursal_id' => $otra->id, 'proveedor_id' => $this->proveedor->id]);
        OrdenCompraItem::factory()->for($ordenAjena, 'orden')->create(['variante_id' => $this->negro37->id]);
        $recepcionAjena = Recepcion::factory()->create(['sucursal_id' => $otra->id, 'proveedor_id' => $this->proveedor->id]);

        $this->actingAs($this->gerente)->get(route('compras.recepciones.create', ['orden' => $ordenAjena->id]))->assertNotFound();
        $this->actingAs($this->gerente)->post(route('compras.recepciones.store'), [
            'orden_compra_id' => $ordenAjena->id,
            'fecha' => now()->toDateString(),
            'items' => [['variante_id' => $this->negro37->id, 'cantidad' => '1', 'costo' => '1']],
        ])->assertNotFound();
        $this->actingAs($this->gerente)->get(route('compras.recepciones.show', $recepcionAjena))->assertNotFound();
        $this->actingAs($this->gerente)->post(route('compras.recepciones.anular', $recepcionAjena), ['motivo' => 'x'])->assertNotFound();
        $this->actingAs($this->gerente)->get(route('compras.recepciones.index'))
            ->assertOk()
            ->assertDontSee(route('compras.recepciones.show', $recepcionAjena));

        $this->assertSame(0, MovimientoStock::count());
        $this->assertFalse($recepcionAjena->refresh()->estaAnulada());
    }

    public function test_reception_form_reloads_items_after_a_validation_error(): void
    {
        $this->actingAs($this->deposito)
            ->from(route('compras.recepciones.create'))
            ->post(route('compras.recepciones.store'), [
                'proveedor_id' => '',
                'fecha' => now()->toDateString(),
                'items' => [['variante_id' => $this->negro37->id, 'cantidad' => '3', 'costo' => '1100']],
            ])
            ->assertRedirect(route('compras.recepciones.create'));

        $this->actingAs($this->deposito)->get(route('compras.recepciones.create'))
            ->assertOk()
            ->assertViewHas('items', fn (array $items) => count($items) === 1
                && $items[0]['variante_id'] === $this->negro37->id
                && $items[0]['cantidad'] === '3');
    }

    /**
     * Datos de una recepción contra la orden: cantidades en el orden de sus ítems.
     *
     * @param  list<int>  $cantidades
     * @return array<string, mixed>
     */
    private function contraOrden(array $cantidades): array
    {
        $variantes = [$this->negro37, $this->negro38];
        $items = [];

        foreach ($cantidades as $posicion => $cantidad) {
            $items[] = ['variante_id' => $variantes[$posicion]->id, 'cantidad' => (string) $cantidad, 'costo' => '1200'];
        }

        return [
            'orden_compra_id' => $this->orden->id,
            'fecha' => now()->toDateString(),
            'referencia' => 'R 0001-00001234',
            'items' => $items,
        ];
    }

    private function vender(Variante $variante, int $cantidad): void
    {
        $this->actingAs($this->gerente);
        DB::transaction(fn () => app(MovimientosStock::class)->registrar($variante, $this->sucursal, TipoMovimientoStock::Egreso, -$cantidad, 'Venta'));
    }

    private function cantidad(Variante $variante): int
    {
        return (int) Stock::query()->where('variante_id', $variante->id)->where('sucursal_id', $this->sucursal->id)->value('cantidad');
    }
}
