<?php

namespace Tests\Feature\Usuarios;

use App\Enums\Rol;
use App\Models\Auditoria;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrearAdministradorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_active_administrator_in_the_main_branch(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('comercio:crear-administrador')
            ->expectsQuestion('Nombre', 'Ana Admin')
            ->expectsQuestion('Email', 'Ana@Example.com')
            ->expectsQuestion('Contraseña', 'una-clave-segura')
            ->expectsQuestion('Repetir contraseña', 'una-clave-segura')
            ->assertSuccessful();

        $user = User::where('email', 'ana@example.com')->sole();
        $principal = Sucursal::where('nombre', Sucursal::PRINCIPAL)->sole();

        $this->assertTrue($user->activo);
        $this->assertTrue(Hash::check('una-clave-segura', $user->password));
        $this->assertSame(Rol::Administrador, $user->rolEn($principal));
        $this->assertTrue(User::administradoresActivos()->whereKey($user->id)->exists());
        $this->assertSame('usuario.creado', Auditoria::sole()->accion);
        $this->assertNull(Auditoria::sole()->user_id);
    }

    public function test_it_rejects_an_email_already_in_use(): void
    {
        $this->seed(DatabaseSeeder::class);
        User::factory()->create(['email' => 'ana@example.com']);

        $this->artisan('comercio:crear-administrador')
            ->expectsQuestion('Nombre', 'Ana Admin')
            ->expectsQuestion('Email', 'ana@example.com')
            ->expectsOutputToContain('El valor de email ya está en uso.')
            ->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_it_rejects_mismatched_passwords(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('comercio:crear-administrador')
            ->expectsQuestion('Nombre', 'Ana Admin')
            ->expectsQuestion('Email', 'ana@example.com')
            ->expectsQuestion('Contraseña', 'una-clave-segura')
            ->expectsQuestion('Repetir contraseña', 'otra-clave')
            ->expectsOutputToContain('Las contraseñas no coinciden.')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_it_requires_the_initial_data(): void
    {
        $this->artisan('comercio:crear-administrador')
            ->expectsOutputToContain('php artisan db:seed')
            ->assertFailed();
    }
}
