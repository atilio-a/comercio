<?php

namespace Tests\Feature\Catalogo;

use App\Enums\Rol;
use App\Enums\TablaMaestra;
use App\Models\AlicuotaIva;
use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\Color;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestionTablasMaestrasTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->gerente = User::factory()->conSucursal(rol: Rol::Gerente)->create();
    }

    public function test_manager_can_create_a_brand(): void
    {
        $this->actingAs($this->gerente)
            ->post(route('catalogo.maestros.store', TablaMaestra::Marcas), ['nombre' => 'Vizzano', 'activo' => '1'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('catalogo.maestros.index', TablaMaestra::Marcas));

        $this->assertTrue(Marca::where('nombre', 'Vizzano')->sole()->activo);
        $this->assertSame('catalogo.marcas.creado', Auditoria::sole()->accion);
    }

    public function test_every_master_table_has_a_working_list_and_form(): void
    {
        foreach (TablaMaestra::cases() as $tabla) {
            $registro = $tabla->modelo()::create(['nombre' => 'Registro de '.$tabla->value]);

            $this->actingAs($this->gerente)->get(route('catalogo.maestros.index', $tabla))
                ->assertOk()
                ->assertSee('Registro de '.$tabla->value);
            $this->actingAs($this->gerente)->get(route('catalogo.maestros.create', $tabla))->assertOk();
            $this->actingAs($this->gerente)->get(route('catalogo.maestros.edit', [$tabla, $registro->id]))->assertOk();
        }
    }

    public function test_unknown_tables_do_not_exist(): void
    {
        $this->actingAs($this->gerente)->get('/catalogo/maestros/proveedores')->assertNotFound();
    }

    public function test_repeated_names_are_rejected_regardless_of_case(): void
    {
        Categoria::create(['nombre' => 'Botas']);

        $this->actingAs($this->gerente)
            ->post(route('catalogo.maestros.store', TablaMaestra::Categorias), ['nombre' => 'botas'])
            ->assertSessionHasErrors(['nombre' => 'Ya existe un registro con ese nombre.']);

        $this->assertSame(1, Categoria::count());
    }

    public function test_deactivation_is_audited_with_previous_and_new_state(): void
    {
        $fucsia = Color::create(['nombre' => 'Fucsia']);

        $this->actingAs($this->gerente)
            ->put(route('catalogo.maestros.update', [TablaMaestra::Colores, $fucsia->id]), ['nombre' => 'Fucsia', 'activo' => '0'])
            ->assertSessionHasNoErrors();

        $this->assertFalse($fucsia->fresh()->activo);
        $registro = Auditoria::sole();
        $this->assertSame('catalogo.colores.actualizado', $registro->accion);
        $this->assertSame(['activo' => true], $registro->antes);
        $this->assertSame(['activo' => false], $registro->despues);
    }

    public function test_users_without_management_permission_get_forbidden(): void
    {
        $vendedor = User::factory()->conSucursal(rol: Rol::Vendedor)->create();
        $categoria = Categoria::create(['nombre' => 'Botas']);

        $this->actingAs($vendedor)->get(route('catalogo.maestros.index', TablaMaestra::Categorias))->assertForbidden();
        $this->actingAs($vendedor)
            ->post(route('catalogo.maestros.store', TablaMaestra::Categorias), ['nombre' => 'Sandalias'])
            ->assertForbidden();
        $this->actingAs($vendedor)
            ->put(route('catalogo.maestros.update', [TablaMaestra::Categorias, $categoria->id]), ['nombre' => 'Otra'])
            ->assertForbidden();

        $this->assertSame(['Botas'], Categoria::pluck('nombre')->all());
    }

    public function test_vat_rate_with_an_invalid_percentage_is_rejected(): void
    {
        $this->actingAs($this->gerente)
            ->post(route('catalogo.alicuotas.store'), ['nombre' => 'Inventada', 'porcentaje' => '150'])
            ->assertSessionHasErrors('porcentaje');

        $this->actingAs($this->gerente)
            ->post(route('catalogo.alicuotas.store'), ['nombre' => 'Muchos decimales', 'porcentaje' => '10.555'])
            ->assertSessionHasErrors('porcentaje');

        $this->assertSame(0, AlicuotaIva::count());
    }

    public function test_manager_can_create_a_vat_rate(): void
    {
        $this->actingAs($this->gerente)
            ->post(route('catalogo.alicuotas.store'), ['nombre' => 'IVA 10,5 %', 'porcentaje' => '10.5', 'activo' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame('10.50', AlicuotaIva::where('nombre', 'IVA 10,5 %')->sole()->porcentaje);
        $this->assertSame('catalogo.alicuotas_iva.creado', Auditoria::sole()->accion);
    }

    public function test_the_percentage_of_a_vat_rate_in_use_cannot_change(): void
    {
        $alicuota = AlicuotaIva::create(['nombre' => 'IVA 21 %', 'porcentaje' => '21']);
        Producto::factory()->for($alicuota, 'alicuotaIva')->create();

        $this->actingAs($this->gerente)
            ->put(route('catalogo.alicuotas.update', $alicuota), ['nombre' => 'IVA 21 %', 'porcentaje' => '22', 'activo' => '1'])
            ->assertSessionHasErrors(['porcentaje' => 'La alícuota está en uso por productos: no se puede cambiar el porcentaje.']);

        $this->assertSame('21.00', $alicuota->fresh()->porcentaje);
    }

    public function test_a_vat_rate_in_use_can_still_be_renamed_or_deactivated(): void
    {
        $alicuota = AlicuotaIva::create(['nombre' => 'IVA 21 %', 'porcentaje' => '21']);
        Producto::factory()->for($alicuota, 'alicuotaIva')->create();

        $this->actingAs($this->gerente)
            ->put(route('catalogo.alicuotas.update', $alicuota), ['nombre' => 'IVA general', 'porcentaje' => '21.00', 'activo' => '0'])
            ->assertSessionHasNoErrors();

        $this->assertSame('IVA general', $alicuota->fresh()->nombre);
        $this->assertFalse($alicuota->fresh()->activo);
    }

    public function test_vat_rates_screens_render(): void
    {
        $alicuota = AlicuotaIva::create(['nombre' => 'IVA 10,5 %', 'porcentaje' => '10.5']);

        $this->actingAs($this->gerente)->get(route('catalogo.alicuotas.index'))->assertOk()->assertSee('10,5 %');
        $this->actingAs($this->gerente)->get(route('catalogo.alicuotas.edit', $alicuota))->assertOk();
    }
}
