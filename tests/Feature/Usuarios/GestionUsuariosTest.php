<?php

namespace Tests\Feature\Usuarios;

use App\Enums\Rol;
use App\Models\Auditoria;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\RegistradorAuditoria;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GestionUsuariosTest extends TestCase
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

    public function test_administrator_can_list_users_with_their_role_per_branch(): void
    {
        User::factory()->conSucursal($this->principal, Rol::Cajero)->create(['name' => 'Carla Cajera']);

        $this->actingAs($this->administrador)->get('/usuarios')
            ->assertOk()
            ->assertSee('Carla Cajera')
            ->assertSee(Sucursal::PRINCIPAL.': Cajero');
    }

    public function test_administrator_can_create_a_user_with_a_role_in_a_branch(): void
    {
        $this->actingAs($this->administrador)
            ->post('/usuarios', $this->datos())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('usuarios.index'));

        $nuevo = User::where('email', 'cajero@example.com')->sole();
        $this->assertTrue($nuevo->activo);
        $this->assertTrue(Hash::check('una-clave-segura', $nuevo->password));
        $this->assertTrue($nuevo->sucursales->contains($this->principal));
        $this->assertSame(Rol::Cajero, $nuevo->rolEn($this->principal));
    }

    public function test_a_created_user_can_log_in_with_the_assigned_role(): void
    {
        $this->actingAs($this->administrador)->post('/usuarios', $this->datos());
        $this->post('/logout');

        $this->post('/login', ['email' => 'cajero@example.com', 'password' => 'una-clave-segura']);
        $this->get('/dashboard')->assertOk();

        $nuevo = User::where('email', 'cajero@example.com')->sole();
        $this->assertTrue($nuevo->hasRole(Rol::Cajero->value));
    }

    public function test_email_must_be_unique(): void
    {
        $this->actingAs($this->administrador)
            ->post('/usuarios', $this->datos(['email' => $this->administrador->email]))
            ->assertSessionHasErrors(['email' => 'El valor de email ya está en uso.']);
    }

    public function test_a_user_needs_at_least_one_branch(): void
    {
        $this->actingAs($this->administrador)
            ->post('/usuarios', $this->datos(['roles' => [$this->principal->id => '']]))
            ->assertSessionHasErrors(['roles' => 'Asigná al menos una sucursal con su rol.']);

        $this->assertDatabaseMissing('users', ['email' => 'cajero@example.com']);
    }

    public function test_unknown_or_disabled_branches_are_rejected(): void
    {
        $inactiva = Sucursal::factory()->inactiva()->create();

        $this->actingAs($this->administrador)
            ->post('/usuarios', $this->datos(['roles' => [$inactiva->id => Rol::Cajero->value]]))
            ->assertSessionHasErrors('roles');
    }

    public function test_users_without_management_permission_cannot_create_or_edit_users(): void
    {
        $vendedor = User::factory()->conSucursal($this->principal, Rol::Vendedor)->create();
        $gerente = User::factory()->conSucursal($this->principal, Rol::Gerente)->create();

        $this->actingAs($vendedor)->get('/usuarios')->assertForbidden();
        $this->actingAs($vendedor)->post('/usuarios', $this->datos())->assertForbidden();

        $this->actingAs($gerente)->get('/usuarios')->assertOk()->assertDontSee('Nuevo usuario');
        $this->actingAs($gerente)->post('/usuarios', $this->datos())->assertForbidden();
        $this->actingAs($gerente)->get(route('usuarios.edit', $vendedor))->assertForbidden();
        $this->actingAs($gerente)
            ->put(route('usuarios.update', $vendedor), $this->datosEdicion($vendedor, ['activo' => '0']))
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'cajero@example.com']);
        $this->assertTrue($vendedor->fresh()->activo);
    }

    public function test_administrator_can_deactivate_a_user_who_keeps_data_and_cannot_log_in(): void
    {
        $cajero = User::factory()->conSucursal($this->principal, Rol::Cajero)->create();

        $this->actingAs($this->administrador)
            ->put(route('usuarios.update', $cajero), $this->datosEdicion($cajero, ['activo' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertFalse($cajero->fresh()->activo);
        $this->assertModelExists($cajero);
        $this->assertSame(Rol::Cajero, $cajero->fresh()->rolEn($this->principal));
    }

    public function test_password_is_kept_when_left_empty_on_edit(): void
    {
        $cajero = User::factory()->conSucursal($this->principal, Rol::Cajero)->create();

        $this->actingAs($this->administrador)
            ->put(route('usuarios.update', $cajero), $this->datosEdicion($cajero, ['name' => 'Nuevo nombre']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Nuevo nombre', $cajero->fresh()->name);
        $this->assertTrue(Hash::check('password', $cajero->fresh()->password));
    }

    public function test_the_last_active_administrator_cannot_be_deactivated_or_demoted_or_unassigned(): void
    {
        $otroAdministrador = User::factory()->conSucursal($this->principal, Rol::Administrador)->create();
        $otroAdministrador->update(['activo' => false]);

        $casos = [
            'desactivar' => ['activo' => '0'],
            'cambiar rol' => ['roles' => [$this->principal->id => Rol::Gerente->value]],
            'quitar sucursal' => ['roles' => [$this->principal->id => '']],
        ];

        // A non-administrator with the management permission, so the editor is not an administrator too.
        $editor = User::factory()->conSucursal($this->principal, Rol::Gerente)->create();
        setPermissionsTeamId($this->principal->id);
        $editor->givePermissionTo('usuarios.gestionar');

        foreach ($casos as $caso => $cambios) {
            $response = $this->actingAs($editor)
                ->put(route('usuarios.update', $this->administrador), $this->datosEdicion($this->administrador, $cambios));

            $errores = $caso === 'quitar sucursal' ? ['roles'] : ['roles' => 'Debe quedar al menos un Administrador activo.'];
            $response->assertSessionHasErrors($errores);
        }

        $this->assertTrue($this->administrador->fresh()->activo);
        $this->assertSame(Rol::Administrador, $this->administrador->fresh()->rolEn($this->principal));
    }

    public function test_an_administrator_can_be_demoted_when_another_active_administrator_remains(): void
    {
        $otro = User::factory()->conSucursal($this->principal, Rol::Administrador)->create();

        $this->actingAs($this->administrador)
            ->put(route('usuarios.update', $otro), $this->datosEdicion($otro, ['roles' => [$this->principal->id => Rol::Gerente->value]]))
            ->assertSessionHasNoErrors();

        $this->assertSame(Rol::Gerente, $otro->fresh()->rolEn($this->principal));
    }

    public function test_a_user_cannot_deactivate_themselves(): void
    {
        User::factory()->conSucursal($this->principal, Rol::Administrador)->create();

        $this->actingAs($this->administrador)
            ->put(route('usuarios.update', $this->administrador), $this->datosEdicion($this->administrador, ['activo' => '0']))
            ->assertSessionHasErrors(['activo' => 'No podés desactivar tu propio usuario.']);

        $this->assertTrue($this->administrador->fresh()->activo);
    }

    public function test_creating_a_user_is_audited_without_the_password(): void
    {
        $this->actingAs($this->administrador)->post('/usuarios', $this->datos());

        $registro = Auditoria::where('accion', 'usuario.creado')->sole();
        $this->assertSame($this->administrador->id, $registro->user_id);
        $this->assertSame('cajero@example.com', $registro->despues['email']);
        $this->assertSame([Sucursal::PRINCIPAL => 'Cajero'], $registro->despues['roles']);
        $this->assertArrayNotHasKey('password', $registro->despues);
        $this->assertStringNotContainsString('una-clave-segura', json_encode($registro->getAttributes()));
    }

    public function test_role_changes_are_audited_with_previous_and_new_role(): void
    {
        $user = User::factory()->conSucursal($this->principal, Rol::Vendedor)->create();

        $this->actingAs($this->administrador)
            ->put(route('usuarios.update', $user), $this->datosEdicion($user, ['roles' => [$this->principal->id => Rol::Cajero->value]]))
            ->assertSessionHasNoErrors();

        $registro = Auditoria::where('accion', 'usuario.actualizado')->sole();
        $this->assertSame($user->id, $registro->modelo_id);
        $this->assertSame([Sucursal::PRINCIPAL => 'Vendedor'], $registro->antes['roles']);
        $this->assertSame([Sucursal::PRINCIPAL => 'Cajero'], $registro->despues['roles']);
    }

    public function test_password_changes_are_audited_without_values(): void
    {
        $user = User::factory()->conSucursal($this->principal, Rol::Vendedor)->create();
        $hashAnterior = $user->password;

        $this->actingAs($this->administrador)
            ->put(route('usuarios.update', $user), $this->datosEdicion($user, [
                'password' => 'otra-clave-segura',
                'password_confirmation' => 'otra-clave-segura',
            ]))
            ->assertSessionHasNoErrors();

        $registro = Auditoria::where('accion', 'usuario.actualizado')->sole();
        $crudo = json_encode($registro->getAttributes());
        $this->assertSame(RegistradorAuditoria::VALOR_OCULTO, $registro->despues['password']);
        $this->assertStringNotContainsString('otra-clave-segura', $crudo);
        $this->assertStringNotContainsString($hashAnterior, $crudo);
        $this->assertStringNotContainsString($user->fresh()->password, $crudo);
    }

    public function test_deactivation_is_audited_as_such(): void
    {
        $user = User::factory()->conSucursal($this->principal, Rol::Vendedor)->create();

        $this->actingAs($this->administrador)
            ->put(route('usuarios.update', $user), $this->datosEdicion($user, ['activo' => '0']));

        $registro = Auditoria::where('accion', 'usuario.desactivado')->sole();
        $this->assertSame(['activo' => true], $registro->antes);
        $this->assertSame(['activo' => false], $registro->despues);
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return array_merge([
            'name' => 'Cajero Nuevo',
            'email' => 'cajero@example.com',
            'password' => 'una-clave-segura',
            'password_confirmation' => 'una-clave-segura',
            'activo' => '1',
            'roles' => [$this->principal->id => Rol::Cajero->value],
        ], $cambios);
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
            'password' => '',
            'password_confirmation' => '',
            'activo' => $user->activo ? '1' : '0',
            'roles' => [$this->principal->id => $user->rolEn($this->principal)?->value ?? ''],
        ], $cambios);
    }
}
