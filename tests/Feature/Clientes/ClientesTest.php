<?php

namespace Tests\Feature\Clientes;

use App\Enums\CondicionIva;
use App\Enums\Parametro;
use App\Enums\Rol;
use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Parametros;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientesTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->sucursal = Sucursal::factory()->create(['nombre' => Sucursal::PRINCIPAL]);
        $this->vendedor = User::factory()->conSucursal($this->sucursal, Rol::Vendedor)->create();
    }

    public function test_salesperson_creates_a_customer_with_a_normalized_document(): void
    {
        $this->actingAs($this->vendedor)->post(route('clientes.store'), [
            'nombre' => 'María López',
            'dni_cuit' => '30.123.456',
            'telefono' => '388 4123456',
            'email' => 'maria@example.com',
        ])->assertSessionHasNoErrors();

        $cliente = Cliente::sole();
        $this->assertSame('30123456', $cliente->dni_cuit);
        $this->assertSame(CondicionIva::ConsumidorFinal, $cliente->condicion_iva);
        $this->assertTrue($cliente->activo);
        $this->assertSame('30.123.456', $cliente->documentoFormateado());

        $auditoria = Auditoria::where('accion', 'clientes.creado')->sole();
        $this->assertSame('30123456', $auditoria->despues['dni_cuit']);
        $this->assertSame('consumidor_final', $auditoria->despues['condicion_iva']);

        $this->actingAs($this->vendedor)->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('María López')
            ->assertSee('30.123.456');
    }

    public function test_invalid_documents_are_rejected(): void
    {
        foreach (['123456', '20-30123456-0', '11-30123456-3', 'abc', '301234567'] as $documento) {
            $this->actingAs($this->vendedor)
                ->post(route('clientes.store'), ['nombre' => 'Cliente', 'dni_cuit' => $documento])
                ->assertSessionHasErrors(['dni_cuit' => 'El documento debe ser un DNI de 7 u 8 dígitos o un CUIT válido.']);
        }

        $this->assertSame(0, Cliente::count());
    }

    public function test_document_must_be_unique_with_or_without_separators(): void
    {
        Cliente::factory()->create(['dni_cuit' => '30123456']);
        Cliente::factory()->create(['dni_cuit' => '20301234563']);

        foreach (['30.123.456', '20-30123456-3'] as $documento) {
            $this->actingAs($this->vendedor)
                ->post(route('clientes.store'), ['nombre' => 'Otro', 'dni_cuit' => $documento])
                ->assertSessionHasErrors(['dni_cuit' => 'Ya hay un cliente con ese documento.']);
        }

        $this->assertSame(2, Cliente::count());
    }

    public function test_customers_without_document_are_allowed_with_a_phone(): void
    {
        foreach (['Uno' => '388 1', 'Dos' => '388 2'] as $nombre => $telefono) {
            $this->actingAs($this->vendedor)
                ->post(route('clientes.store'), ['nombre' => $nombre, 'dni_cuit' => '', 'telefono' => $telefono])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame([null, null], Cliente::pluck('dni_cuit')->all());
    }

    public function test_a_phone_or_a_document_is_required(): void
    {
        $this->actingAs($this->vendedor)
            ->post(route('clientes.store'), ['nombre' => 'Solo nombre'])
            ->assertSessionHasErrors(['telefono' => 'Indicá un teléfono o un documento.']);

        $this->assertSame(0, Cliente::count());
    }

    public function test_tax_conditions_other_than_final_consumer_require_a_cuit(): void
    {
        $this->actingAs($this->vendedor)
            ->post(route('clientes.store'), [
                'nombre' => 'Empresa',
                'dni_cuit' => '30123456',
                'condicion_iva' => CondicionIva::ResponsableInscripto->value,
            ])
            ->assertSessionHasErrors(['dni_cuit' => 'Para un cliente Responsable inscripto indicá su CUIT.']);

        $this->actingAs($this->vendedor)
            ->post(route('clientes.store'), [
                'nombre' => 'Empresa',
                'dni_cuit' => '30-71234567-1',
                'condicion_iva' => CondicionIva::ResponsableInscripto->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(CondicionIva::ResponsableInscripto, Cliente::sole()->condicion_iva);
    }

    public function test_editing_keeps_its_own_document_and_is_audited(): void
    {
        $cliente = Cliente::factory()->create(['dni_cuit' => '30123456', 'telefono' => '388 1']);

        $this->actingAs($this->vendedor)->put(route('clientes.update', $cliente), [
            'nombre' => $cliente->nombre,
            'dni_cuit' => '30.123.456',
            'telefono' => '388 2',
            'activo' => '0',
        ])->assertRedirect(route('clientes.show', $cliente))->assertSessionHasNoErrors();

        $cliente->refresh();
        $this->assertSame('388 2', $cliente->telefono);
        $this->assertFalse($cliente->activo);

        $auditoria = Auditoria::where('accion', 'clientes.actualizado')->sole();
        $this->assertSame('388 1', $auditoria->antes['telefono']);
        $this->assertSame('388 2', $auditoria->despues['telefono']);
        $this->assertArrayNotHasKey('dni_cuit', $auditoria->despues);
    }

    public function test_list_searches_by_name_document_and_phone(): void
    {
        $maria = Cliente::factory()->create(['nombre' => 'María López', 'dni_cuit' => '30123456', 'telefono' => '388 4111111']);
        $juan = Cliente::factory()->create(['nombre' => 'Juan Pérez', 'dni_cuit' => '20301234563', 'telefono' => '388 5222222']);
        $ana = Cliente::factory()->create(['nombre' => 'Ana Gómez', 'dni_cuit' => '25111222', 'telefono' => '388 6333333']);

        $buscar = fn (string $q) => $this->actingAs($this->vendedor)->get(route('clientes.index', ['q' => $q]))->assertOk();

        $buscar('lópez')->assertSee($maria->nombre)->assertDontSee($juan->nombre)->assertDontSee($ana->nombre);
        // A DNI with dots also finds the CUIT that contains it.
        $buscar('30.123.456')->assertSee($maria->nombre)->assertSee($juan->nombre)->assertDontSee($ana->nombre);
        $buscar('6333333')->assertSee($ana->nombre)->assertDontSee($maria->nombre);
    }

    public function test_json_search_returns_active_customers_only(): void
    {
        $maria = Cliente::factory()->create(['nombre' => 'María López', 'dni_cuit' => '30123456', 'telefono' => '388 4111111']);
        Cliente::factory()->inactivo()->create(['nombre' => 'Marta López']);
        Cliente::factory()->create(['nombre' => 'Juan Pérez']);
        $cajero = User::factory()->conSucursal($this->sucursal, Rol::Cajero)->create();

        $this->actingAs($cajero)->getJson(route('clientes.buscar', ['q' => 'lóp']))
            ->assertOk()
            ->assertExactJson(['clientes' => [[
                'id' => $maria->id,
                'nombre' => 'María López',
                'documento' => '30.123.456',
                'telefono' => '388 4111111',
                'condicion_iva' => 'consumidor_final',
                'talle_habitual' => null,
            ]]]);

        $this->actingAs($cajero)->getJson(route('clientes.buscar'))->assertUnprocessable();
    }

    public function test_quick_creation_returns_the_customer_as_json(): void
    {
        $respuesta = $this->actingAs($this->vendedor)->postJson(route('clientes.rapido'), [
            'nombre' => 'Juan Pérez',
            'telefono' => '388 5001122',
        ]);

        $cliente = Cliente::sole();
        $respuesta->assertCreated()->assertJsonPath('cliente.id', $cliente->id)->assertJsonPath('cliente.nombre', 'Juan Pérez');
        $this->assertSame(CondicionIva::ConsumidorFinal, $cliente->condicion_iva);
        $this->assertTrue($cliente->activo);
        $this->assertSame(1, Auditoria::where('accion', 'clientes.creado')->count());
    }

    public function test_invalid_quick_creation_returns_validation_errors(): void
    {
        $this->actingAs($this->vendedor)->postJson(route('clientes.rapido'), ['nombre' => 'Sin datos'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['telefono']);

        $this->actingAs($this->vendedor)->postJson(route('clientes.rapido'), ['nombre' => 'X', 'dni_cuit' => '12'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dni_cuit']);

        $this->assertSame(0, Cliente::count());
    }

    public function test_usual_size_is_hidden_and_kept_when_sizes_are_disabled(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Ana Gómez', 'talle_habitual' => '38']);
        app(Parametros::class)->guardar(Parametro::UsarTalles, false);

        $this->actingAs($this->vendedor)->get(route('clientes.show', $cliente))
            ->assertOk()
            ->assertDontSee('Talle habitual');
        $this->actingAs($this->vendedor)->get(route('clientes.edit', $cliente))
            ->assertOk()
            ->assertDontSee('name="talle_habitual"', false);
        $this->actingAs($this->vendedor)->getJson(route('clientes.buscar', ['q' => 'Ana']))
            ->assertOk()
            ->assertJsonMissingPath('clientes.0.talle_habitual');

        $this->actingAs($this->vendedor)->put(route('clientes.update', $cliente), [
            'nombre' => 'Ana Gómez',
            'telefono' => '388 9',
            'talle_habitual' => '',
            'activo' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('38', $cliente->fresh()->talle_habitual);
        $this->assertSame('388 9', $cliente->fresh()->telefono);
    }

    public function test_usual_size_is_editable_when_sizes_are_enabled(): void
    {
        $cliente = Cliente::factory()->create(['talle_habitual' => '38']);

        $this->actingAs($this->vendedor)->get(route('clientes.edit', $cliente))
            ->assertOk()
            ->assertSee('name="talle_habitual"', false);

        $this->actingAs($this->vendedor)->put(route('clientes.update', $cliente), [
            'nombre' => $cliente->nombre,
            'telefono' => $cliente->telefono,
            'talle_habitual' => '39',
            'activo' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('39', $cliente->fresh()->talle_habitual);
        $this->actingAs($this->vendedor)->get(route('clientes.show', $cliente))
            ->assertOk()
            ->assertSee('Talle habitual')
            ->assertSee('39');
    }

    public function test_warehouse_cannot_see_customers(): void
    {
        $deposito = User::factory()->conSucursal($this->sucursal, Rol::Deposito)->create();
        $cliente = Cliente::factory()->create();

        $this->actingAs($deposito)->get(route('clientes.index'))->assertForbidden();
        $this->actingAs($deposito)->get(route('clientes.show', $cliente))->assertForbidden();
        $this->actingAs($deposito)->getJson(route('clientes.buscar', ['q' => 'a']))->assertForbidden();
        $this->actingAs($deposito)->get(route('clientes.create'))->assertForbidden();
        $this->actingAs($deposito)->post(route('clientes.store'), ['nombre' => 'X', 'telefono' => '1'])->assertForbidden();
        $this->actingAs($deposito)->postJson(route('clientes.rapido'), ['nombre' => 'X', 'telefono' => '1'])->assertForbidden();
        $this->actingAs($deposito)->put(route('clientes.update', $cliente), ['nombre' => 'X', 'telefono' => '1'])->assertForbidden();

        $this->assertSame(1, Cliente::count());
    }

    public function test_cashier_and_manager_manage_customers(): void
    {
        foreach ([Rol::Cajero, Rol::Gerente] as $rol) {
            $user = User::factory()->conSucursal($this->sucursal, $rol)->create();

            $this->actingAs($user)->get(route('clientes.create'))->assertOk();
            $this->actingAs($user)
                ->post(route('clientes.store'), ['nombre' => 'Cliente de '.$rol->value, 'telefono' => '388 '.$rol->value])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, Cliente::count());
    }

    public function test_customer_profile_reserves_the_sales_history(): void
    {
        $cliente = Cliente::factory()->create();

        $this->actingAs($this->vendedor)->get(route('clientes.show', $cliente))
            ->assertOk()
            ->assertSee($cliente->nombre)
            ->assertSee('El historial de ventas del cliente estará disponible con el módulo de ventas.');
    }
}
