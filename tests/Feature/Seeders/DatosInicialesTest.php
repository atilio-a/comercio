<?php

namespace Tests\Feature\Seeders;

use App\Enums\Permiso;
use App\Enums\Rol;
use App\Models\Caja;
use App\Models\MedioPago;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatosInicialesTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_data_creates_the_main_branch_roles_and_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame([Sucursal::PRINCIPAL], Sucursal::activas()->pluck('nombre')->all());
        $this->assertEqualsCanonicalizing(
            array_column(Rol::cases(), 'value'),
            Role::whereNull('team_id')->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            array_column(Permiso::cases(), 'value'),
            Permission::pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['usuarios.ver', 'auditoria.ver', 'catalogo.ver', 'catalogo.gestionar', 'stock.ver', 'stock.ajustar', 'caja.operar', 'caja.supervisar', 'compras.ver', 'compras.gestionar', 'compras.recibir', 'clientes.ver', 'clientes.gestionar', 'cuentas_corrientes.ver', 'ventas.vender', 'ventas.ver_todas', 'ventas.autorizar_descuentos', 'ventas.anular'],
            Role::findByName(Rol::Gerente->value)->permissions->pluck('name')->all(),
        );

        $this->assertEqualsCanonicalizing(
            ['catalogo.ver', 'stock.ver', 'caja.operar', 'clientes.ver', 'clientes.gestionar', 'ventas.vender'],
            Role::findByName(Rol::Vendedor->value)->permissions->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['catalogo.ver', 'stock.ver', 'caja.operar', 'clientes.ver', 'clientes.gestionar', 'cuentas_corrientes.ver', 'ventas.vender', 'ventas.ver_todas'],
            Role::findByName(Rol::Cajero->value)->permissions->pluck('name')->all(),
        );

        $this->assertEqualsCanonicalizing(
            ['catalogo.ver', 'stock.ver', 'stock.ajustar', 'compras.ver', 'compras.recibir'],
            Role::findByName(Rol::Deposito->value)->permissions->pluck('name')->all(),
        );

        $this->assertSame(7, MedioPago::count());
        $this->assertSame([Caja::PRINCIPAL], Caja::pluck('nombre')->all());
        $this->assertSame([1], PuntoVenta::where('activo', true)->pluck('numero')->all());
    }

    public function test_initial_data_can_be_seeded_twice_without_duplicates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Sucursal::count());
        $this->assertSame(count(Rol::cases()), Role::count());
        $this->assertSame(count(Permiso::cases()), Permission::count());
        $this->assertSame(7, MedioPago::count());
        $this->assertSame(1, Caja::count());
        $this->assertSame(1, PuntoVenta::count());
    }

    public function test_initial_data_does_not_create_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::count());
    }
}
