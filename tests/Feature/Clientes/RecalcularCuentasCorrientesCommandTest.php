<?php

namespace Tests\Feature\Clientes;

use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\CuentaCorriente;
use App\Models\Sucursal;
use App\Services\CuentasCorrientes;
use App\Services\SucursalActiva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecalcularCuentasCorrientesCommandTest extends TestCase
{
    use RefreshDatabase;

    private CuentaCorriente $cuenta;

    protected function setUp(): void
    {
        parent::setUp();

        app(SucursalActiva::class)->establecer(Sucursal::factory()->create());
        $cliente = Cliente::factory()->create();

        DB::transaction(function () use ($cliente) {
            $cuentas = app(CuentasCorrientes::class);
            $cuentas->acreditar($cliente, '5000.00', 'Devolución');
            $cuentas->debitar($cliente, '1250.50', 'Pago con saldo a favor');
        });

        $this->cuenta = $cliente->cuentaCorriente()->sole();
    }

    public function test_matching_balances_finish_successfully(): void
    {
        $this->artisan('cuentas-corrientes:recalcular')
            ->expectsOutputToContain('coinciden')
            ->assertSuccessful();
    }

    public function test_differences_are_reported_and_fail_without_fixing(): void
    {
        $this->cuenta->forceFill(['saldo' => '0.00'])->save();

        $this->artisan('cuentas-corrientes:recalcular')
            ->expectsOutputToContain('Hay 1 diferencias')
            ->assertFailed();

        $this->assertSame('0.00', $this->cuenta->fresh()->saldo);
    }

    public function test_differences_are_fixed_and_audited(): void
    {
        $this->cuenta->forceFill(['saldo' => '0.00'])->save();
        $sinMovimientos = CuentaCorriente::factory()->create(['saldo' => '10.00']);

        $this->artisan('cuentas-corrientes:recalcular', ['--corregir' => true])->assertSuccessful();

        $this->assertSame('-3749.50', $this->cuenta->fresh()->saldo);
        $this->assertSame('0.00', $sinMovimientos->fresh()->saldo);

        $auditoria = Auditoria::where('accion', 'cuenta_corriente.recalculada')->where('modelo_id', $this->cuenta->id)->sole();
        $this->assertSame(['saldo' => '0.00'], $auditoria->antes);
        $this->assertSame(['saldo' => '-3749.50'], $auditoria->despues);

        $this->artisan('cuentas-corrientes:recalcular')->assertSuccessful();
    }
}
