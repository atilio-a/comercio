<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->conSucursal()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->conSucursal()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
    }

    public function test_user_cannot_delete_their_account(): void
    {
        $user = User::factory()->conSucursal()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response->assertMethodNotAllowed();

        $this->assertNotNull($user->fresh());
    }

    public function test_profile_changes_are_audited(): void
    {
        $user = User::factory()->conSucursal()->create(['name' => 'Nombre anterior']);

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Nombre nuevo',
            'email' => $user->email,
        ])->assertSessionHasNoErrors();

        $registro = Auditoria::sole();
        $this->assertSame('usuario.perfil_actualizado', $registro->accion);
        $this->assertSame(['name' => 'Nombre anterior'], $registro->antes);
        $this->assertSame(['name' => 'Nombre nuevo'], $registro->despues);
    }

    public function test_saving_the_profile_without_changes_is_not_audited(): void
    {
        $user = User::factory()->conSucursal()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, Auditoria::count());
    }
}
