<?php

namespace Tests\Feature\Usuarios;

use App\Enums\Rol;
use App\Models\Auditoria;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Vendedor;
use App\Services\SucursalActiva;
use App\Services\Vendedores;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendedoresTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $principal;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->principal = Sucursal::factory()->create(['nombre' => Sucursal::PRINCIPAL]);
        $this->administrador = User::factory()->conSucursal($this->principal, Rol::Administrador)->create();
    }

    public function test_a_user_can_be_created_as_salesperson(): void
    {
        $this->actingAs($this->administrador)->post(route('usuarios.store'), [
            'name' => 'Valeria Vendedora',
            'email' => 'valeria@example.com',
            'password' => 'una-clave-segura',
            'password_confirmation' => 'una-clave-segura',
            'roles' => [$this->principal->id => Rol::Vendedor->value],
            'vendedor' => '1',
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'valeria@example.com')->sole();
        $this->assertTrue($user->esVendedor());
        $this->assertTrue(Auditoria::where('accion', 'usuario.creado')->sole()->despues['vendedor']);

        $this->actingAs($this->administrador)->get(route('usuarios.index'))
            ->assertOk()
            ->assertSeeInOrder(['Valeria Vendedora', 'Vendedor</span>'], false);
    }

    public function test_unchecking_deactivates_the_salesperson_and_is_audited(): void
    {
        $user = User::factory()->conSucursal($this->principal, Rol::Vendedor)->create();
        Vendedor::factory()->create(['user_id' => $user->id]);

        $this->actingAs($this->administrador)->get(route('usuarios.edit', $user))
            ->assertOk()
            ->assertSee('Es vendedor');

        $this->actingAs($this->administrador)
            ->put(route('usuarios.update', $user), $this->datosEdicion($user, ['vendedor' => '0']))
            ->assertSessionHasNoErrors();

        $vendedor = Vendedor::sole();
        $this->assertFalse($vendedor->activo);
        $this->assertFalse($user->fresh()->esVendedor());

        $registro = Auditoria::where('accion', 'usuario.actualizado')->sole();
        $this->assertSame(['vendedor' => true], $registro->antes);
        $this->assertSame(['vendedor' => false], $registro->despues);

        $this->actingAs($this->administrador)
            ->put(route('usuarios.update', $user), $this->datosEdicion($user, ['vendedor' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Vendedor::count());
        $this->assertTrue(Vendedor::sole()->activo);
    }

    public function test_editing_without_the_field_does_not_change_the_mark(): void
    {
        $user = User::factory()->conSucursal($this->principal, Rol::Vendedor)->create();
        Vendedor::factory()->create(['user_id' => $user->id]);

        $this->actingAs($this->administrador)
            ->put(route('usuarios.update', $user), $this->datosEdicion($user, ['name' => 'Otro nombre']))
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->esVendedor());
        $this->assertArrayNotHasKey('vendedor', Auditoria::where('accion', 'usuario.actualizado')->sole()->despues);
    }

    public function test_available_salespeople_are_active_users_assigned_to_the_branch(): void
    {
        $otraSucursal = Sucursal::factory()->create();

        $disponible = Vendedor::factory()->for(User::factory()->conSucursal($this->principal)->state(['name' => 'Beatriz']))->create();
        $primero = Vendedor::factory()->for(User::factory()->conSucursal($this->principal)->state(['name' => 'Ana']))->create();
        Vendedor::factory()->for(User::factory()->conSucursal($otraSucursal))->create();
        Vendedor::factory()->inactivo()->for(User::factory()->conSucursal($this->principal))->create();
        Vendedor::factory()->for(User::factory()->inactivo()->conSucursal($this->principal))->create();
        User::factory()->conSucursal($this->principal)->create();

        app(SucursalActiva::class)->establecer($this->principal);
        $vendedores = app(Vendedores::class);

        $this->assertSame([$primero->id, $disponible->id], $vendedores->disponibles()->modelKeys());
        $this->assertCount(1, $vendedores->disponibles($otraSucursal));

        app(SucursalActiva::class)->olvidar();
        $this->assertCount(0, $vendedores->disponibles());
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datosEdicion(User $user, array $cambios = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'activo' => '1',
            'roles' => [$this->principal->id => Rol::Vendedor->value],
        ], $cambios);
    }
}
