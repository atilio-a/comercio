<?php

namespace Tests\Feature\Clientes;

use App\Enums\Rol;
use App\Models\CcMovimiento;
use App\Models\Cliente;
use App\Models\CuentaCorriente;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CuentasCorrientes;
use App\Services\SucursalActiva;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EstadoCuentaTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->sucursal = Sucursal::factory()->create(['nombre' => Sucursal::PRINCIPAL]);
        $this->cliente = Cliente::factory()->create();
    }

    public function test_cashier_sees_the_credit_balance_and_movements(): void
    {
        $this->registrarMovimientos();
        $cajero = User::factory()->conSucursal($this->sucursal, Rol::Cajero)->create();

        $this->actingAs($cajero)->get(route('clientes.show', $this->cliente))
            ->assertOk()
            ->assertSee('Cuenta corriente')
            ->assertSee('Saldo a favor')
            ->assertSee('3.000,00')
            ->assertSee('Sin crédito habilitado')
            ->assertSeeInOrder(['Pago con saldo a favor', 'Devolución ticket 15'])
            ->assertSee('Contraasiento');
    }

    public function test_salesperson_does_not_see_the_account(): void
    {
        $this->registrarMovimientos();
        $vendedor = User::factory()->conSucursal($this->sucursal, Rol::Vendedor)->create();

        $this->actingAs($vendedor)->get(route('clientes.show', $this->cliente))
            ->assertOk()
            ->assertDontSee('Cuenta corriente')
            ->assertDontSee('Saldo a favor')
            ->assertDontSee('Devolución ticket 15');
    }

    public function test_opening_the_profile_does_not_create_an_account(): void
    {
        $gerente = User::factory()->conSucursal($this->sucursal, Rol::Gerente)->create();

        $this->actingAs($gerente)->get(route('clientes.show', $this->cliente))
            ->assertOk()
            ->assertSee('El cliente todavía no tiene movimientos en cuenta corriente.');

        $this->assertSame(0, CuentaCorriente::count());
    }

    public function test_debt_is_shown_as_debt(): void
    {
        CuentaCorriente::factory()->conCredito()->create(['cliente_id' => $this->cliente->id]);
        $this->comoSistema(fn (CuentasCorrientes $cuentas) => $cuentas->debitar($this->cliente, '1500.00', 'Venta a cuenta'));
        $gerente = User::factory()->conSucursal($this->sucursal, Rol::Gerente)->create();

        $this->actingAs($gerente)->get(route('clientes.show', $this->cliente))
            ->assertOk()
            ->assertSee('Deuda')
            ->assertSee('1.500,00')
            ->assertDontSee('Saldo a favor');
    }

    private function registrarMovimientos(): void
    {
        $this->comoSistema(function (CuentasCorrientes $cuentas) {
            $cuentas->acreditar($this->cliente, '8000.00', 'Devolución ticket 15');
            $error = $cuentas->acreditar($this->cliente, '100.00', 'Carga equivocada');
            $cuentas->contraasentar($error, 'Error de carga');
            $cuentas->debitar($this->cliente, '5000.00', 'Pago con saldo a favor');
        });

        $this->assertSame(4, CcMovimiento::count());
    }

    /**
     * @param  callable(CuentasCorrientes): mixed  $operacion
     */
    private function comoSistema(callable $operacion): void
    {
        app(SucursalActiva::class)->establecer($this->sucursal);
        DB::transaction(fn () => $operacion(app(CuentasCorrientes::class)));
        app(SucursalActiva::class)->olvidar();
    }
}
