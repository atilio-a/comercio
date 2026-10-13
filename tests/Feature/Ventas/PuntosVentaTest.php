<?php

namespace Tests\Feature\Ventas;

use App\Enums\Rol;
use App\Models\Auditoria;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\PuntoVentaSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PuntosVentaTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->sucursal = Sucursal::factory()->create(['nombre' => Sucursal::PRINCIPAL]);
        $this->administrador = User::factory()->conSucursal($this->sucursal, Rol::Administrador)->create();
    }

    public function test_the_administrator_creates_a_point_of_sale_with_audit(): void
    {
        $this->actingAs($this->administrador)->get(route('ventas.puntos-venta.create'))->assertOk()->assertSee('Nuevo punto de venta');

        $this->actingAs($this->administrador)
            ->post(route('ventas.puntos-venta.store'), ['numero' => 2, 'descripcion' => 'Mostrador planta alta', 'activo' => '1'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('ventas.puntos-venta.index'));

        $puntoVenta = PuntoVenta::sole();
        $this->assertSame($this->sucursal->id, $puntoVenta->sucursal_id);
        $this->assertSame(2, $puntoVenta->numero);
        $this->assertTrue($puntoVenta->activo);
        $this->assertTrue(Auditoria::where('accion', 'puntos_venta.creado')->where('modelo_id', $puntoVenta->id)->exists());

        $this->actingAs($this->administrador)->get(route('ventas.puntos-venta.index'))
            ->assertOk()
            ->assertSee('00002 – Mostrador planta alta');
    }

    public function test_the_number_is_unique_across_branches(): void
    {
        PuntoVenta::factory()->create(['numero' => 3]);

        $this->actingAs($this->administrador)
            ->post(route('ventas.puntos-venta.store'), ['numero' => 3])
            ->assertSessionHasErrors(['numero' => 'Ya hay un punto de venta con ese número.']);

        foreach ([0, 100000, 'abc'] as $numero) {
            $this->actingAs($this->administrador)
                ->post(route('ventas.puntos-venta.store'), ['numero' => $numero])
                ->assertSessionHasErrors('numero');
        }
    }

    public function test_editing_changes_description_and_status_but_not_the_number(): void
    {
        $puntoVenta = PuntoVenta::factory()->create(['sucursal_id' => $this->sucursal->id, 'numero' => 1]);

        $this->actingAs($this->administrador)->get(route('ventas.puntos-venta.edit', $puntoVenta))
            ->assertOk()
            ->assertSee('El número no se puede cambiar.');

        $this->actingAs($this->administrador)
            ->put(route('ventas.puntos-venta.update', $puntoVenta), ['numero' => 9, 'descripcion' => 'Caja'])
            ->assertSessionHasErrors('numero');

        $this->actingAs($this->administrador)
            ->put(route('ventas.puntos-venta.update', $puntoVenta), ['descripcion' => 'Caja', 'activo' => '0'])
            ->assertSessionHasNoErrors();

        $puntoVenta->refresh();
        $this->assertSame(1, $puntoVenta->numero);
        $this->assertSame('Caja', $puntoVenta->descripcion);
        $this->assertFalse($puntoVenta->activo);
        $this->assertTrue(Auditoria::where('accion', 'puntos_venta.actualizado')->exists());
    }

    public function test_only_the_administrator_configures_points_of_sale(): void
    {
        $puntoVenta = PuntoVenta::factory()->create(['sucursal_id' => $this->sucursal->id, 'numero' => 1]);

        foreach ([Rol::Gerente, Rol::Cajero, Rol::Vendedor, Rol::Deposito] as $rol) {
            $user = User::factory()->conSucursal($this->sucursal, $rol)->create();

            $this->actingAs($user)->get(route('ventas.puntos-venta.index'))->assertForbidden();
            $this->actingAs($user)->post(route('ventas.puntos-venta.store'), ['numero' => 5])->assertForbidden();
            $this->actingAs($user)->put(route('ventas.puntos-venta.update', $puntoVenta), ['descripcion' => 'X'])->assertForbidden();
        }

        $this->assertSame(1, PuntoVenta::count());
    }

    public function test_points_of_sale_of_another_branch_do_not_exist(): void
    {
        $ajeno = PuntoVenta::factory()->create(['numero' => 4]);

        $this->actingAs($this->administrador)->get(route('ventas.puntos-venta.edit', $ajeno))->assertNotFound();
        $this->actingAs($this->administrador)->put(route('ventas.puntos-venta.update', $ajeno), ['descripcion' => 'X'])->assertNotFound();
        $this->actingAs($this->administrador)->get(route('ventas.puntos-venta.index'))->assertOk()->assertDontSee('00004');
    }

    public function test_the_seeder_creates_one_point_of_sale_per_active_branch_with_the_lowest_free_number(): void
    {
        $segunda = Sucursal::factory()->create();
        Sucursal::factory()->create(['activa' => false]);

        $this->seed(PuntoVentaSeeder::class);

        $this->assertSame(1, PuntoVenta::where('sucursal_id', $this->sucursal->id)->value('numero'));
        $this->assertSame(2, PuntoVenta::where('sucursal_id', $segunda->id)->value('numero'));
        $this->assertSame(2, PuntoVenta::count());

        $this->seed(PuntoVentaSeeder::class);
        $this->assertSame(2, PuntoVenta::count());
    }
}
