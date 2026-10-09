<?php

namespace Tests\Feature\Sucursales;

use App\Enums\Parametro;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Parametros;
use App\Services\SucursalActiva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelectorSucursalTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $centro;

    private Sucursal $norte;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centro = Sucursal::factory()->create(['nombre' => 'Centro']);
        $this->norte = Sucursal::factory()->create(['nombre' => 'Norte']);
        $this->user = User::factory()->conSucursal($this->centro)->conSucursal($this->norte)->create();
    }

    public function test_selector_does_not_exist_while_multi_branch_is_disabled(): void
    {
        $this->actingAs($this->user)->get('/sucursal')->assertNotFound();

        $this->put('/sucursal', ['sucursal_id' => $this->norte->id])->assertForbidden();
    }

    public function test_with_multi_branch_disabled_the_first_assigned_branch_is_used(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertOk()
            ->assertSessionHas(SucursalActiva::CLAVE_SESION, $this->centro->id);
    }

    public function test_with_multi_branch_enabled_a_user_with_several_branches_must_choose_one(): void
    {
        $this->habilitarMultiSucursal();

        $this->actingAs($this->user)->get('/dashboard')->assertRedirect(route('sucursal-activa.edit'));

        $this->get('/sucursal')
            ->assertOk()
            ->assertSeeInOrder(['Centro', 'Norte']);
    }

    public function test_user_can_switch_to_another_assigned_branch(): void
    {
        $this->habilitarMultiSucursal();

        $this->actingAs($this->user)
            ->withSession([SucursalActiva::CLAVE_SESION => $this->centro->id])
            ->put('/sucursal', ['sucursal_id' => $this->norte->id])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(SucursalActiva::CLAVE_SESION, $this->norte->id);

        $this->get('/dashboard')->assertOk();
        $this->assertSame($this->norte->id, app(SucursalActiva::class)->id());
    }

    public function test_user_cannot_select_a_branch_that_is_not_assigned(): void
    {
        $this->habilitarMultiSucursal();
        $ajena = Sucursal::factory()->create();

        $this->actingAs($this->user)
            ->withSession([SucursalActiva::CLAVE_SESION => $this->centro->id])
            ->put('/sucursal', ['sucursal_id' => $ajena->id])
            ->assertForbidden();

        $this->assertSame($this->centro->id, session(SucursalActiva::CLAVE_SESION));
    }

    public function test_user_cannot_select_a_disabled_branch(): void
    {
        $this->habilitarMultiSucursal();
        $this->norte->update(['activa' => false]);

        $this->actingAs($this->user)
            ->put('/sucursal', ['sucursal_id' => $this->norte->id])
            ->assertForbidden();
    }

    private function habilitarMultiSucursal(): void
    {
        app(Parametros::class)->guardar(Parametro::MultiSucursal, true);
    }
}
