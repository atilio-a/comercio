<?php

namespace Tests\Feature\Auth;

use App\Models\Auditoria;
use App\Models\User;
use App\Services\RegistradorAuditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->conSucursal()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->conSucursal()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }

    public function test_password_change_is_audited_without_the_password(): void
    {
        $user = User::factory()->conSucursal()->create();

        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors();

        $registro = Auditoria::sole();
        $this->assertSame('usuario.contrasena_cambiada', $registro->accion);
        $this->assertSame($user->id, $registro->user_id);
        $this->assertSame(RegistradorAuditoria::VALOR_OCULTO, $registro->despues['password']);
        $this->assertStringNotContainsString('new-password', json_encode($registro->getAttributes()));
        $this->assertStringNotContainsString($user->fresh()->password, json_encode($registro->getAttributes()));
    }
}
