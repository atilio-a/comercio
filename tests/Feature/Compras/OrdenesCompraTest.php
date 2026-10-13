<?php

namespace Tests\Feature\Compras;

use App\Enums\EstadoOrdenCompra;
use App\Enums\Rol;
use App\Models\Auditoria;
use App\Models\Color;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Variante;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class OrdenesCompraTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $gerente;

    private Proveedor $proveedor;

    private Variante $negro37;

    private Variante $negro38;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->sucursal = Sucursal::factory()->create(['nombre' => Sucursal::PRINCIPAL]);
        $this->gerente = User::factory()->conSucursal($this->sucursal, Rol::Gerente)->create();
        $this->proveedor = Proveedor::factory()->create(['razon_social' => 'Calzados del Norte SA']);
        $producto = Producto::factory()->create(['nombre' => 'Bota Ana']);
        $negro = Color::factory()->create(['nombre' => 'Negro']);
        $this->negro37 = Variante::factory()->for($producto)->for($negro)->create(['talle' => '37', 'sku' => 'BOTA-N-37', 'codigo_barras' => '7790000000037']);
        $this->negro38 = Variante::factory()->for($producto)->for($negro)->create(['talle' => '38', 'sku' => 'BOTA-N-38', 'codigo_barras' => '7790000000038']);
    }

    public function test_manager_creates_a_draft_order_with_items(): void
    {
        $this->actingAs($this->gerente)->post(route('compras.ordenes.store'), $this->datos())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('compras.ordenes.show', OrdenCompra::sole()));

        $orden = OrdenCompra::sole();
        $this->assertSame(1, $orden->numero);
        $this->assertSame(EstadoOrdenCompra::Borrador, $orden->estado);
        $this->assertSame($this->sucursal->id, $orden->sucursal_id);
        $this->assertSame($this->gerente->id, $orden->user_id);
        $this->assertSame(
            [[$this->negro37->id, 10, 0, '1500.50'], [$this->negro38->id, 5, 0, '1500.00']],
            $orden->items()->orderBy('id')->get()->map(fn ($item) => [$item->variante_id, $item->cantidad_pedida, $item->cantidad_recibida, $item->costo])->all(),
        );
        $this->assertSame(1, Auditoria::where('accion', 'compras.orden.creada')->count());

        $this->actingAs($this->gerente)->get(route('compras.ordenes.show', $orden))
            ->assertOk()
            ->assertSee('Orden N° 1')
            ->assertSee('Borrador')
            ->assertSee('Bota Ana')
            ->assertSee('22.505,00')
            ->assertSee('Emitir orden');
    }

    public function test_order_form_renders_for_create_and_edit(): void
    {
        $this->actingAs($this->gerente)->get(route('compras.ordenes.create'))
            ->assertOk()
            ->assertSee('Nueva orden de compra')
            ->assertSee('Calzados del Norte SA')
            ->assertSee('Escanear código')
            ->assertSee('urlBuscar', false);

        $orden = $this->crearOrden();

        $this->actingAs($this->gerente)->get(route('compras.ordenes.edit', $orden))
            ->assertOk()
            ->assertSee('Editar orden N° '.$orden->numero)
            ->assertViewHas('items', fn (array $items) => array_column($items, 'variante_id') === [$this->negro37->id, $this->negro38->id]);
    }

    public function test_numbers_are_correlative_per_branch(): void
    {
        $otra = Sucursal::factory()->create();
        OrdenCompra::factory()->create(['sucursal_id' => $otra->id]);

        $this->actingAs($this->gerente)->post(route('compras.ordenes.store'), $this->datos())->assertSessionHasNoErrors();
        $this->actingAs($this->gerente)->post(route('compras.ordenes.store'), $this->datos())->assertSessionHasNoErrors();

        $this->assertSame([1, 2], OrdenCompra::where('sucursal_id', $this->sucursal->id)->orderBy('numero')->pluck('numero')->all());
        $this->assertSame([1], OrdenCompra::where('sucursal_id', $otra->id)->pluck('numero')->all());
    }

    public function test_invalid_orders_are_rejected(): void
    {
        $repetida = $this->datos();
        $repetida['items'][1]['variante_id'] = $this->negro37->id;
        $this->actingAs($this->gerente)->post(route('compras.ordenes.store'), $repetida)
            ->assertSessionHasErrors('items.1.variante_id');

        $inactivo = Proveedor::factory()->inactivo()->create();
        $this->actingAs($this->gerente)->post(route('compras.ordenes.store'), ['proveedor_id' => $inactivo->id] + $this->datos())
            ->assertSessionHasErrors('proveedor_id');

        $this->actingAs($this->gerente)->post(route('compras.ordenes.store'), ['items' => []] + $this->datos())
            ->assertSessionHasErrors('items');

        $sinStock = $this->datos();
        $sinStock['items'][0]['cantidad'] = '0';
        $sinStock['items'][1]['costo'] = '10.505';
        $this->actingAs($this->gerente)->post(route('compras.ordenes.store'), $sinStock)
            ->assertSessionHasErrors(['items.0.cantidad', 'items.1.costo']);

        $this->negro38->update(['activo' => false]);
        $this->actingAs($this->gerente)->post(route('compras.ordenes.store'), $this->datos())
            ->assertSessionHasErrors('items.1.variante_id');

        $this->assertSame(0, OrdenCompra::count());
    }

    public function test_draft_is_edited_and_emitted_order_is_not(): void
    {
        $orden = $this->crearOrden();

        $datos = $this->datos();
        unset($datos['items'][1]);
        $datos['items'][0]['cantidad'] = '3';
        $this->actingAs($this->gerente)->put(route('compras.ordenes.update', $orden), $datos)->assertSessionHasNoErrors();

        $this->assertSame([[$this->negro37->id, 3]], $orden->items()->get()->map(fn ($item) => [$item->variante_id, $item->cantidad_pedida])->all());
        $this->assertSame(1, Auditoria::where('accion', 'compras.orden.actualizada')->count());

        $this->actingAs($this->gerente)->post(route('compras.ordenes.emitir', $orden))->assertSessionHasNoErrors();
        $this->assertSame(EstadoOrdenCompra::Emitida, $orden->refresh()->estado);
        $this->assertSame(1, Auditoria::where('accion', 'compras.orden.emitida')->count());

        $this->actingAs($this->gerente)->put(route('compras.ordenes.update', $orden), $this->datos())->assertSessionHasErrors('orden');
        $this->actingAs($this->gerente)->get(route('compras.ordenes.edit', $orden))->assertRedirect(route('compras.ordenes.show', $orden));
        $this->actingAs($this->gerente)->post(route('compras.ordenes.emitir', $orden))->assertSessionHasErrors('orden');
        $this->assertSame(1, $orden->items()->count());
    }

    public function test_order_without_receptions_is_cancelled_and_audited(): void
    {
        $orden = $this->crearOrden(EstadoOrdenCompra::Emitida);

        $this->actingAs($this->gerente)->post(route('compras.ordenes.anular', $orden), ['motivo' => ''])
            ->assertSessionHasErrorsIn('anulacion', 'motivo');

        $this->actingAs($this->gerente)->post(route('compras.ordenes.anular', $orden), ['motivo' => 'El proveedor no tiene stock'])
            ->assertSessionHasNoErrors();

        $this->assertSame(EstadoOrdenCompra::Anulada, $orden->refresh()->estado);
        $auditoria = Auditoria::where('accion', 'compras.orden.anulada')->sole();
        $this->assertSame('El proveedor no tiene stock', $auditoria->despues['motivo']);

        $this->actingAs($this->gerente)->post(route('compras.ordenes.emitir', $orden))->assertSessionHasErrors('orden');
    }

    public function test_order_with_received_goods_cannot_be_cancelled(): void
    {
        $orden = $this->crearOrden(EstadoOrdenCompra::Parcial);
        $orden->items()->first()->update(['cantidad_recibida' => 4]);

        $this->actingAs($this->gerente)->post(route('compras.ordenes.anular', $orden), ['motivo' => 'Error'])
            ->assertSessionHasErrorsIn('anulacion', 'motivo');

        $this->assertSame(EstadoOrdenCompra::Parcial, $orden->refresh()->estado);
        $this->assertSame(0, Auditoria::where('accion', 'compras.orden.anulada')->count());
    }

    public function test_warehouse_cannot_manage_orders_and_cashier_cannot_see_them(): void
    {
        $orden = $this->crearOrden();
        $deposito = User::factory()->conSucursal($this->sucursal, Rol::Deposito)->create();
        $cajero = User::factory()->conSucursal($this->sucursal, Rol::Cajero)->create();

        $this->actingAs($deposito)->get(route('compras.ordenes.index'))->assertOk()->assertSee('Orden N° 1')->assertDontSee(route('compras.ordenes.create'));
        $this->actingAs($deposito)->get(route('compras.ordenes.show', $orden))->assertOk()->assertDontSee('Emitir orden');
        $this->actingAs($deposito)->get(route('compras.ordenes.create'))->assertForbidden();
        $this->actingAs($deposito)->post(route('compras.ordenes.store'), $this->datos())->assertForbidden();
        $this->actingAs($deposito)->post(route('compras.ordenes.emitir', $orden))->assertForbidden();
        $this->actingAs($deposito)->post(route('compras.ordenes.anular', $orden), ['motivo' => 'x'])->assertForbidden();

        $this->actingAs($cajero)->get(route('compras.ordenes.index'))->assertForbidden();
        $this->actingAs($cajero)->get(route('compras.ordenes.show', $orden))->assertForbidden();
    }

    public function test_orders_of_another_branch_do_not_exist(): void
    {
        $ajena = OrdenCompra::factory()->create(['sucursal_id' => Sucursal::factory()->create()->id, 'proveedor_id' => $this->proveedor->id]);
        $this->crearOrden();

        $this->actingAs($this->gerente)->get(route('compras.ordenes.index'))
            ->assertOk()
            ->assertSee(route('compras.ordenes.show', OrdenCompra::where('sucursal_id', $this->sucursal->id)->sole()))
            ->assertDontSee(route('compras.ordenes.show', $ajena));
        $this->actingAs($this->gerente)->get(route('compras.ordenes.show', $ajena))->assertNotFound();
        $this->actingAs($this->gerente)->get(route('compras.ordenes.edit', $ajena))->assertNotFound();
        $this->actingAs($this->gerente)->post(route('compras.ordenes.emitir', $ajena))->assertNotFound();
        $this->actingAs($this->gerente)->post(route('compras.ordenes.anular', $ajena), ['motivo' => 'x'])->assertNotFound();
        $this->assertSame(EstadoOrdenCompra::Borrador, $ajena->refresh()->estado);
    }

    public function test_variant_search_by_name_sku_and_exact_code(): void
    {
        Variante::factory()->inactivo()->for($this->negro37->producto)->create(['sku' => 'BOTA-INACTIVA']);

        $this->actingAs($this->gerente)->getJson(route('compras.variantes.buscar', ['q' => 'bota']))
            ->assertOk()
            ->assertJsonCount(2, 'variantes')
            ->assertJsonPath('variantes.0.producto', 'Bota Ana')
            ->assertJsonPath('variantes.0.descripcion', 'Negro / 37')
            ->assertJsonPath('variantes.0.costo', '1000.00');

        $this->actingAs($this->gerente)->getJson(route('compras.variantes.buscar', ['q' => '7790000000038', 'exacto' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'variantes')
            ->assertJsonPath('variantes.0.variante_id', $this->negro38->id);

        $this->actingAs($this->gerente)->getJson(route('compras.variantes.buscar', ['q' => 'Bota', 'exacto' => 1]))
            ->assertOk()
            ->assertJsonCount(0, 'variantes');

        $this->actingAs($this->gerente)->getJson(route('compras.variantes.buscar', ['q' => 'BOTA-INACTIVA']))
            ->assertOk()
            ->assertJsonCount(0, 'variantes');

        $vendedor = User::factory()->conSucursal($this->sucursal, Rol::Vendedor)->create();
        $this->actingAs($vendedor)->getJson(route('compras.variantes.buscar', ['q' => 'bota']))->assertForbidden();
    }

    public function test_status_follows_received_quantities(): void
    {
        $orden = $this->crearOrden(EstadoOrdenCompra::Emitida);
        $orden->load('items');
        $this->assertSame(EstadoOrdenCompra::Emitida, $orden->estadoSegunRecepciones());

        $orden->items[0]->cantidad_recibida = 4;
        $this->assertSame(EstadoOrdenCompra::Parcial, $orden->estadoSegunRecepciones());
        $this->assertSame(6, $orden->items[0]->pendiente());

        $orden->items[0]->cantidad_recibida = 10;
        $orden->items[1]->cantidad_recibida = 5;
        $this->assertSame(EstadoOrdenCompra::Recibida, $orden->estadoSegunRecepciones());
    }

    public function test_orders_are_never_deleted(): void
    {
        $orden = $this->crearOrden();

        $this->expectException(LogicException::class);
        $orden->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(): array
    {
        return [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => now()->toDateString(),
            'observaciones' => 'Entrega en el local',
            'items' => [
                ['variante_id' => $this->negro37->id, 'cantidad' => '10', 'costo' => '1500.5'],
                ['variante_id' => $this->negro38->id, 'cantidad' => '5', 'costo' => '1500'],
            ],
        ];
    }

    private function crearOrden(EstadoOrdenCompra $estado = EstadoOrdenCompra::Borrador): OrdenCompra
    {
        $orden = OrdenCompra::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'proveedor_id' => $this->proveedor->id,
            'user_id' => $this->gerente->id,
            'estado' => $estado,
        ]);
        OrdenCompraItem::factory()->for($orden, 'orden')->create(['variante_id' => $this->negro37->id, 'cantidad_pedida' => 10]);
        OrdenCompraItem::factory()->for($orden, 'orden')->create(['variante_id' => $this->negro38->id, 'cantidad_pedida' => 5]);

        return $orden;
    }
}
