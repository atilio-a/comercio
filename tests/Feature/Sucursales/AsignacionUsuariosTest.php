<?php

namespace Tests\Feature\Sucursales;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsignacionUsuariosTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_be_assigned_to_several_branches(): void
    {
        $user = User::factory()->create();
        [$centro, $norte] = Sucursal::factory()->count(2)->create();

        $user->sucursales()->attach([$centro->id, $norte->id]);

        $this->assertEqualsCanonicalizing(
            [$centro->id, $norte->id],
            $user->sucursales()->pluck('sucursales.id')->all(),
        );
        $this->assertTrue($centro->users->contains($user));
    }

    public function test_the_same_branch_cannot_be_assigned_twice(): void
    {
        $user = User::factory()->create();
        $sucursal = Sucursal::factory()->create();

        $user->sucursales()->attach($sucursal);

        $this->expectException(QueryException::class);

        $user->sucursales()->attach($sucursal);
    }

    public function test_active_scope_excludes_disabled_branches(): void
    {
        $activa = Sucursal::factory()->create();
        Sucursal::factory()->inactiva()->create();

        $this->assertSame([$activa->id], Sucursal::activas()->pluck('id')->all());
    }

    public function test_users_are_active_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->fresh()->activo);
    }
}
