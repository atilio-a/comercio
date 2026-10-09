<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Rol;
use App\Models\Auditoria;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultaAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $gerente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->sucursal = Sucursal::factory()->create();
        $this->gerente = User::factory()->conSucursal($this->sucursal, Rol::Gerente)->create();
    }

    public function test_records_are_listed_from_newest_to_oldest(): void
    {
        $this->registrar('primera', '2026-10-01 10:00:00');
        $this->registrar('segunda', '2026-10-02 10:00:00');
        $this->registrar('tercera', '2026-10-03 10:00:00');

        $this->actingAs($this->gerente)->get('/auditoria')
            ->assertOk()
            ->assertSeeInOrder(['tercera', 'segunda', 'primera']);
    }

    public function test_records_can_be_filtered_by_user_and_period(): void
    {
        $cajero = User::factory()->conSucursal($this->sucursal, Rol::Cajero)->create();
        $this->registrar('del-cajero-en-octubre', '2026-10-05 12:00:00', $cajero);
        $this->registrar('del-cajero-en-septiembre', '2026-09-20 12:00:00', $cajero);
        $this->registrar('del-gerente-en-octubre', '2026-10-06 12:00:00', $this->gerente);

        $this->actingAs($this->gerente)
            ->get('/auditoria?'.http_build_query(['user_id' => $cajero->id, 'desde' => '2026-10-01', 'hasta' => '2026-10-31']))
            ->assertOk()
            ->assertViewHas('registros', fn ($registros) => $registros->pluck('accion')->all() === ['del-cajero-en-octubre']);
    }

    public function test_records_can_be_filtered_by_action(): void
    {
        $this->registrar('parametro.modificado', '2026-10-05 12:00:00');
        $this->registrar('usuario.creado', '2026-10-05 13:00:00');

        $this->actingAs($this->gerente)->get('/auditoria?accion=usuario.creado')
            ->assertOk()
            ->assertViewHas('registros', fn ($registros) => $registros->pluck('accion')->all() === ['usuario.creado']);
    }

    public function test_only_records_of_the_active_branch_are_shown(): void
    {
        $otra = Sucursal::factory()->create();
        $this->registrar('de-esta-sucursal', '2026-10-05 12:00:00');
        $this->registrar('de-otra-sucursal', '2026-10-05 12:00:00', sucursal: $otra);

        $this->actingAs($this->gerente)->get('/auditoria')
            ->assertOk()
            ->assertSee('de-esta-sucursal')
            ->assertDontSee('de-otra-sucursal')
            ->assertViewHas('registros', fn ($registros) => $registros->pluck('accion')->all() === ['de-esta-sucursal']);
    }

    public function test_users_without_permission_get_forbidden(): void
    {
        $vendedor = User::factory()->conSucursal($this->sucursal, Rol::Vendedor)->create();

        $this->actingAs($vendedor)->get('/auditoria')->assertForbidden();
    }

    public function test_invalid_period_is_rejected(): void
    {
        $this->actingAs($this->gerente)
            ->from('/auditoria')
            ->get('/auditoria?desde=2026-10-10&hasta=2026-10-01')
            ->assertSessionHasErrors('hasta');
    }

    public function test_there_are_no_routes_to_edit_or_delete_records(): void
    {
        $registro = $this->registrar('intocable', '2026-10-05 12:00:00');

        $this->actingAs($this->gerente)->delete('/auditoria/'.$registro->id)->assertStatus(404);
        $this->actingAs($this->gerente)->put('/auditoria/'.$registro->id, ['accion' => 'x'])->assertStatus(404);
        $this->assertSame('intocable', $registro->fresh()->accion);
    }

    private function registrar(string $accion, string $momento, ?User $user = null, ?Sucursal $sucursal = null): Auditoria
    {
        $this->travelTo($momento);

        $registro = Auditoria::create([
            'user_id' => ($user ?? $this->gerente)->id,
            'sucursal_id' => ($sucursal ?? $this->sucursal)->id,
            'accion' => $accion,
        ]);

        $this->travelBack();

        return $registro;
    }
}
