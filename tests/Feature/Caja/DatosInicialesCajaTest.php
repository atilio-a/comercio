<?php

namespace Tests\Feature\Caja;

use App\Enums\TipoMedioPago;
use App\Models\Caja;
use App\Models\MedioPago;
use App\Models\Sucursal;
use Database\Seeders\CajaSeeder;
use Database\Seeders\MedioPagoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatosInicialesCajaTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_payment_methods_are_created(): void
    {
        $this->seed(MedioPagoSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['Efectivo', 'Tarjeta de débito', 'Tarjeta de crédito', 'Transferencia', 'QR / billetera', 'Cuenta corriente', 'Saldo a favor'],
            MedioPago::pluck('nombre')->all(),
        );
        $this->assertSame(['Efectivo'], MedioPago::where('impacta_caja', true)->pluck('nombre')->all());
        $this->assertEqualsCanonicalizing(
            array_column(TipoMedioPago::cases(), 'value'),
            MedioPago::pluck('tipo')->map->value->all(),
        );

        $credito = MedioPago::where('tipo', TipoMedioPago::TarjetaCredito->value)->sole();
        $plan = $credito->planes()->sole();
        $this->assertSame(1, $plan->cuotas);
        $this->assertSame('0.00', $plan->recargo_pct);
    }

    public function test_seeding_twice_does_not_duplicate_nor_overwrite_changes(): void
    {
        $this->seed(MedioPagoSeeder::class);
        MedioPago::where('nombre', 'QR / billetera')->update(['activo' => false]);

        $this->seed(MedioPagoSeeder::class);

        $this->assertSame(7, MedioPago::count());
        $this->assertFalse(MedioPago::where('nombre', 'QR / billetera')->sole()->activo);
        $this->assertSame(1, MedioPago::where('tipo', TipoMedioPago::TarjetaCredito->value)->sole()->planes()->count());
    }

    public function test_each_active_branch_gets_one_main_cash_register(): void
    {
        $centro = Sucursal::factory()->create();
        $norte = Sucursal::factory()->create();
        $inactiva = Sucursal::factory()->inactiva()->create();

        $this->seed(CajaSeeder::class);
        $this->seed(CajaSeeder::class);

        foreach ([$centro, $norte] as $sucursal) {
            $this->assertSame([Caja::PRINCIPAL], Caja::where('sucursal_id', $sucursal->id)->pluck('nombre')->all());
        }

        $this->assertSame(0, Caja::where('sucursal_id', $inactiva->id)->count());
    }
}
