<?php

namespace Tests\Feature\Sucursales;

use App\Enums\Parametro;
use App\Enums\Rol;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Parametros;
use App\Services\SucursalActiva;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SucursalActivaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
    }

    public function test_a_user_with_one_branch_gets_it_as_active_branch_automatically(): void
    {
        $principal = Sucursal::factory()->create(['nombre' => Sucursal::PRINCIPAL]);
        $user = User::factory()->conSucursal($principal)->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSessionHas(SucursalActiva::CLAVE_SESION, $principal->id);

        $this->assertSame($principal->id, app(SucursalActiva::class)->id());
        $this->assertSame($principal->id, getPermissionsTeamId());
    }

    public function test_inactive_users_cannot_log_in_even_with_valid_credentials(): void
    {
        $user = User::factory()->inactivo()->conSucursal()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.inactive')]);

        $this->assertGuest();
    }

    public function test_a_user_deactivated_during_the_session_is_logged_out_on_the_next_request(): void
    {
        $user = User::factory()->conSucursal()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['activo' => false]);

        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => __('auth.inactive')]);
        $this->assertGuest();
    }

    public function test_inactive_users_do_not_receive_password_reset_links(): void
    {
        Notification::fake();
        $user = User::factory()->inactivo()->conSucursal()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status', __('passwords.sent'));

        Notification::assertNothingSentTo($user);
        Notification::assertNotSentTo($user, ResetPassword::class);
    }

    public function test_a_user_whose_only_branch_is_disabled_is_logged_out(): void
    {
        $user = User::factory()->conSucursal(Sucursal::factory()->inactiva()->create())->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => __('auth.no_branches')]);
        $this->assertGuest();
    }

    public function test_a_user_without_branches_is_logged_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => __('auth.no_branches')]);
    }

    public function test_permissions_come_from_the_role_in_the_active_branch(): void
    {
        $centro = Sucursal::factory()->create();
        $norte = Sucursal::factory()->create();
        $user = User::factory()->conSucursal($centro, Rol::Gerente)->conSucursal($norte, Rol::Vendedor)->create();
        app(Parametros::class)->guardar(Parametro::MultiSucursal, true);

        $this->actingAs($user)
            ->withSession([SucursalActiva::CLAVE_SESION => $norte->id])
            ->get('/dashboard')
            ->assertOk();
        $this->assertFalse($user->can('usuarios.ver'));

        $this->withSession([SucursalActiva::CLAVE_SESION => $centro->id])->get('/dashboard')->assertOk();
        $this->assertTrue($user->can('usuarios.ver'));
    }

    public function test_administrators_have_every_permission_in_their_branch_including_unknown_ones(): void
    {
        $user = User::factory()->conSucursal(rol: Rol::Administrador)->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertTrue($user->can('modulo-futuro.accion'));
        $this->assertTrue($user->can('parametros.editar'));
    }

    public function test_administrator_role_in_another_branch_does_not_grant_permissions_in_the_active_one(): void
    {
        $centro = Sucursal::factory()->create();
        $norte = Sucursal::factory()->create();
        $user = User::factory()->conSucursal($centro, Rol::Administrador)->conSucursal($norte, Rol::Cajero)->create();
        app(Parametros::class)->guardar(Parametro::MultiSucursal, true);

        $this->actingAs($user)
            ->withSession([SucursalActiva::CLAVE_SESION => $norte->id])
            ->get('/dashboard')
            ->assertOk();

        $this->assertFalse($user->can('parametros.editar'));
    }
}
