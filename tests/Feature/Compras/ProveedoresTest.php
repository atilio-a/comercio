<?php

namespace Tests\Feature\Compras;

use App\Enums\Rol;
use App\Models\Auditoria;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProveedoresTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $sucursal = Sucursal::factory()->create(['nombre' => Sucursal::PRINCIPAL]);
        $this->gerente = User::factory()->conSucursal($sucursal, Rol::Gerente)->create();
    }

    public function test_manager_creates_a_supplier_with_a_normalized_cuit(): void
    {
        $this->actingAs($this->gerente)->post(route('compras.proveedores.store'), [
            'razon_social' => 'Calzados del Norte SA',
            'cuit' => '30-71234567-1',
            'contacto' => 'Ana',
            'telefono' => '388 4123456',
            'email' => 'ventas@example.com',
            'condiciones_pago' => '30 días',
        ])->assertRedirect(route('compras.proveedores.index'))->assertSessionHasNoErrors();

        $proveedor = Proveedor::sole();
        $this->assertSame('30712345671', $proveedor->cuit);
        $this->assertTrue($proveedor->activo);
        $this->assertSame('30-71234567-1', $proveedor->cuitFormateado());

        $auditoria = Auditoria::where('accion', 'compras.proveedores.creado')->sole();
        $this->assertSame('30712345671', $auditoria->despues['cuit']);

        $this->actingAs($this->gerente)->get(route('compras.proveedores.index'))
            ->assertOk()
            ->assertSee('Calzados del Norte SA')
            ->assertSee('30-71234567-1');
    }

    public function test_invalid_cuit_is_rejected(): void
    {
        foreach (['30-71234567-2', '1234', '11-71234567-1', 'abc'] as $cuit) {
            $this->actingAs($this->gerente)
                ->post(route('compras.proveedores.store'), ['razon_social' => 'Proveedor', 'cuit' => $cuit])
                ->assertSessionHasErrors('cuit');
        }

        $this->assertSame(0, Proveedor::count());
    }

    public function test_cuit_must_be_unique_with_or_without_dashes(): void
    {
        Proveedor::factory()->create(['cuit' => '30712345671']);

        $this->actingAs($this->gerente)
            ->post(route('compras.proveedores.store'), ['razon_social' => 'Otro', 'cuit' => '30-71234567-1'])
            ->assertSessionHasErrors(['cuit' => 'Ya hay un proveedor con ese CUIT.']);
    }

    public function test_suppliers_without_cuit_are_allowed(): void
    {
        foreach (['Uno', 'Dos'] as $nombre) {
            $this->actingAs($this->gerente)
                ->post(route('compras.proveedores.store'), ['razon_social' => $nombre, 'cuit' => ''])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame([null, null], Proveedor::pluck('cuit')->all());
    }

    public function test_editing_keeps_its_own_cuit_and_is_audited(): void
    {
        $proveedor = Proveedor::factory()->create(['cuit' => '30712345671', 'condiciones_pago' => 'Contado']);

        $this->actingAs($this->gerente)->put(route('compras.proveedores.update', $proveedor), [
            'razon_social' => $proveedor->razon_social,
            'cuit' => '30712345671',
            'condiciones_pago' => '60 días',
            'activo' => '0',
        ])->assertSessionHasNoErrors();

        $proveedor->refresh();
        $this->assertSame('60 días', $proveedor->condiciones_pago);
        $this->assertFalse($proveedor->activo);

        $auditoria = Auditoria::where('accion', 'compras.proveedores.actualizado')->sole();
        $this->assertSame('Contado', $auditoria->antes['condiciones_pago']);
        $this->assertFalse($auditoria->despues['activo']);
    }

    public function test_warehouse_sees_suppliers_but_cannot_manage_them(): void
    {
        $deposito = User::factory()->conSucursal(rol: Rol::Deposito)->create();
        $proveedor = Proveedor::factory()->create();

        $this->actingAs($deposito)->get(route('compras.proveedores.index'))
            ->assertOk()
            ->assertSee($proveedor->razon_social)
            ->assertDontSee(route('compras.proveedores.create'));
        $this->actingAs($deposito)->get(route('compras.proveedores.create'))->assertForbidden();
        $this->actingAs($deposito)->post(route('compras.proveedores.store'), ['razon_social' => 'X'])->assertForbidden();
        $this->actingAs($deposito)->get(route('compras.proveedores.edit', $proveedor))->assertForbidden();
    }

    public function test_salesperson_cannot_see_suppliers(): void
    {
        $vendedor = User::factory()->conSucursal(rol: Rol::Vendedor)->create();

        $this->actingAs($vendedor)->get(route('compras.proveedores.index'))->assertForbidden();
    }
}
