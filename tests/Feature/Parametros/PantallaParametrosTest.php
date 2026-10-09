<?php

namespace Tests\Feature\Parametros;

use App\Enums\Parametro;
use App\Enums\Rol;
use App\Models\Auditoria;
use App\Models\User;
use App\Services\Parametros;
use App\Services\RegistradorAuditoria;
use Database\Seeders\ParametroSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PantallaParametrosTest extends TestCase
{
    use RefreshDatabase;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesYPermisosSeeder::class, ParametroSeeder::class]);
        $this->administrador = User::factory()->conSucursal(rol: Rol::Administrador)->create();
    }

    public function test_administrator_sees_every_parameter_with_its_help(): void
    {
        $response = $this->actingAs($this->administrador)->get('/parametros')->assertOk();

        foreach (Parametro::cases() as $parametro) {
            $response->assertSee($parametro->etiqueta())->assertSee($parametro->ayuda());
        }
    }

    public function test_administrator_can_enable_negative_stock(): void
    {
        $this->actingAs($this->administrador)
            ->put('/parametros', $this->formulario([Parametro::StockNegativo->value => '1']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('parametros.edit'));

        $this->assertTrue(app(Parametros::class)->habilitado(Parametro::StockNegativo));
    }

    public function test_users_without_permission_cannot_view_or_change_parameters(): void
    {
        foreach ([Rol::Gerente, Rol::Vendedor, Rol::Cajero, Rol::Deposito] as $rol) {
            $user = User::factory()->conSucursal(rol: $rol)->create();

            $this->actingAs($user)->get('/parametros')->assertForbidden();
            $this->actingAs($user)
                ->put('/parametros', $this->formulario([Parametro::StockNegativo->value => '1']))
                ->assertForbidden();
        }

        $this->assertFalse(app(Parametros::class)->habilitado(Parametro::StockNegativo));
        $this->assertSame(0, Auditoria::count());
    }

    public function test_unknown_parameter_keys_are_rejected(): void
    {
        $this->actingAs($this->administrador)
            ->put('/parametros', $this->formulario(['inventado' => '1']))
            ->assertSessionHasErrors('parametros');

        $this->assertDatabaseMissing('parametros', ['clave' => 'inventado']);
    }

    public function test_values_that_are_not_yes_or_no_are_rejected(): void
    {
        $this->actingAs($this->administrador)
            ->put('/parametros', $this->formulario([Parametro::MultiSucursal->value => 'tal vez']))
            ->assertSessionHasErrors('parametros.multi_sucursal');

        $this->assertFalse(app(Parametros::class)->habilitado(Parametro::MultiSucursal));
    }

    public function test_electronic_invoicing_cannot_be_enabled_yet(): void
    {
        $this->actingAs($this->administrador)
            ->put('/parametros', $this->formulario([Parametro::FacturacionElectronica->value => '1']))
            ->assertSessionHasErrors([
                'parametros.facturacion_electronica' => 'Facturación electrónica todavía no está disponible.',
            ]);

        $this->assertFalse(app(Parametros::class)->habilitado(Parametro::FacturacionElectronica));
    }

    public function test_each_change_is_audited_with_previous_and_new_value(): void
    {
        $this->actingAs($this->administrador)
            ->put('/parametros', $this->formulario([Parametro::MultiSucursal->value => '1']))
            ->assertSessionHasNoErrors();

        $registro = Auditoria::sole();
        $this->assertSame($this->administrador->id, $registro->user_id);
        $this->assertSame('parametro.modificado', $registro->accion);
        $this->assertSame(['multi_sucursal' => 'deshabilitado'], $registro->antes);
        $this->assertSame(['multi_sucursal' => 'habilitado'], $registro->despues);
    }

    public function test_saving_without_changes_does_not_create_audit_records(): void
    {
        $this->actingAs($this->administrador)
            ->put('/parametros', $this->formulario())
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Auditoria::count());
    }

    public function test_if_auditing_fails_the_parameter_change_is_rolled_back(): void
    {
        $this->mock(RegistradorAuditoria::class)
            ->shouldReceive('registrar')
            ->andThrow(new RuntimeException('falla simulada'));

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->administrador)
                ->put('/parametros', $this->formulario([Parametro::StockNegativo->value => '1']));
            $this->fail('Se esperaba la falla simulada.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertFalse(app(Parametros::class)->habilitado(Parametro::StockNegativo));
        $this->assertDatabaseHas('parametros', ['clave' => 'stock_negativo', 'valor' => '0']);
    }

    /**
     * Formulario completo con los valores actuales, más los cambios indicados.
     *
     * @param  array<string, string>  $cambios
     * @return array{parametros: array<string, string>}
     */
    private function formulario(array $cambios = []): array
    {
        $actuales = array_map(fn (bool $valor) => $valor ? '1' : '0', app(Parametros::class)->todos());

        return ['parametros' => array_merge($actuales, $cambios)];
    }
}
