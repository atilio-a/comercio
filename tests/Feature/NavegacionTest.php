<?php

namespace Tests\Feature;

use App\Enums\Parametro;
use App\Enums\Rol;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Parametros;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavegacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
    }

    public function test_cashier_does_not_see_users_parameters_or_audit(): void
    {
        $cajero = User::factory()->conSucursal(rol: Rol::Cajero)->create();

        $this->actingAs($cajero)->get('/dashboard')
            ->assertOk()
            ->assertDontSee(route('usuarios.index'))
            ->assertDontSee(route('parametros.edit'))
            ->assertDontSee(route('auditoria.index'));
    }

    public function test_administrator_sees_users_parameters_and_audit(): void
    {
        $administrador = User::factory()->conSucursal(rol: Rol::Administrador)->create();

        $this->actingAs($administrador)->get('/dashboard')
            ->assertOk()
            ->assertSee(route('usuarios.index'))
            ->assertSee(route('parametros.edit'))
            ->assertSee(route('auditoria.index'));
    }

    public function test_manager_sees_users_and_audit_but_not_parameters(): void
    {
        $gerente = User::factory()->conSucursal(rol: Rol::Gerente)->create();

        $this->actingAs($gerente)->get('/dashboard')
            ->assertOk()
            ->assertSee(route('usuarios.index'))
            ->assertSee(route('auditoria.index'))
            ->assertDontSee(route('parametros.edit'));
    }

    public function test_branch_selector_is_hidden_while_multi_branch_is_disabled(): void
    {
        $user = User::factory()->conSucursal(rol: Rol::Administrador)->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertDontSee(route('sucursal-activa.edit'))
            ->assertDontSee('Sucursal:');
    }

    public function test_branch_selector_is_shown_when_multi_branch_is_enabled(): void
    {
        $principal = Sucursal::factory()->create(['nombre' => Sucursal::PRINCIPAL]);
        $user = User::factory()->conSucursal($principal, Rol::Administrador)->create();
        app(Parametros::class)->guardar(Parametro::MultiSucursal, true);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee(route('sucursal-activa.edit'))
            ->assertSee(Sucursal::PRINCIPAL);
    }

    public function test_mobile_menu_button_is_accessible(): void
    {
        $user = User::factory()->conSucursal()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('aria-label="Menú"', false)
            ->assertSee('aria-controls="menu-movil"', false);
    }
}
