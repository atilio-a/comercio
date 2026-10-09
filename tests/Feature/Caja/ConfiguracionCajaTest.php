<?php

namespace Tests\Feature\Caja;

use App\Enums\Rol;
use App\Enums\TipoMedioPago;
use App\Models\Auditoria;
use App\Models\Caja;
use App\Models\MedioPago;
use App\Models\PlanCuota;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfiguracionCajaTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->sucursal = Sucursal::factory()->create(['nombre' => Sucursal::PRINCIPAL]);
        $this->administrador = User::factory()->conSucursal($this->sucursal, Rol::Administrador)->create();
    }

    public function test_administrator_creates_a_payment_method_and_it_is_audited(): void
    {
        $this->actingAs($this->administrador)->get(route('caja.medios-pago.create'))->assertOk();

        $this->actingAs($this->administrador)->post(route('caja.medios-pago.store'), [
            'nombre' => 'Mercado Pago',
            'tipo' => TipoMedioPago::Qr->value,
            'impacta_caja' => '0',
            'dias_acreditacion' => '1',
            'requiere_referencia' => '1',
            'activo' => '1',
        ])->assertRedirect(route('caja.medios-pago.index'))->assertSessionHasNoErrors();

        $medio = MedioPago::where('nombre', 'Mercado Pago')->sole();
        $this->assertSame(TipoMedioPago::Qr, $medio->tipo);
        $this->assertFalse($medio->impacta_caja);
        $this->assertSame(1, $medio->dias_acreditacion);
        $this->assertTrue($medio->requiere_referencia);
        $this->assertTrue($medio->activo);
        $this->assertTrue(Auditoria::where('accion', 'caja.medios_pago.creado')->where('modelo_id', $medio->id)->exists());

        $this->actingAs($this->administrador)->get(route('caja.medios-pago.index'))->assertOk()->assertSee('Mercado Pago');
    }

    public function test_payment_method_names_are_unique(): void
    {
        MedioPago::factory()->create(['nombre' => 'Transferencia']);

        $this->actingAs($this->administrador)->post(route('caja.medios-pago.store'), [
            'nombre' => 'Transferencia',
            'tipo' => TipoMedioPago::Transferencia->value,
            'dias_acreditacion' => '0',
        ])->assertSessionHasErrors('nombre');

        $this->assertSame(1, MedioPago::count());
    }

    public function test_deactivating_a_payment_method_is_audited(): void
    {
        $medio = MedioPago::factory()->create(['nombre' => 'Transferencia']);

        $this->actingAs($this->administrador)->put(route('caja.medios-pago.update', $medio), [
            'nombre' => 'Transferencia',
            'tipo' => TipoMedioPago::Transferencia->value,
            'dias_acreditacion' => '0',
            'activo' => '0',
        ])->assertRedirect(route('caja.medios-pago.index'));

        $this->assertFalse($medio->fresh()->activo);
        $auditoria = Auditoria::where('accion', 'caja.medios_pago.actualizado')->sole();
        $this->assertSame(['activo' => true], $auditoria->antes);
        $this->assertSame(['activo' => false], $auditoria->despues);
    }

    public function test_installment_plan_with_surcharge(): void
    {
        $credito = MedioPago::factory()->tipo(TipoMedioPago::TarjetaCredito)->create();

        $this->actingAs($this->administrador)->get(route('caja.medios-pago.edit', $credito))->assertOk()->assertSee('Planes de cuotas');
        $this->actingAs($this->administrador)->post(route('caja.medios-pago.planes.store', $credito), [
            'cuotas' => '3',
            'recargo_pct' => '12.5',
        ])->assertRedirect(route('caja.medios-pago.edit', $credito))->assertSessionHasNoErrors();

        $plan = $credito->planes()->sole();
        $this->assertSame(3, $plan->cuotas);
        $this->assertSame('12.50', $plan->recargo_pct);
        $this->assertTrue($plan->activo);
        $this->assertTrue(Auditoria::where('accion', 'caja.planes_cuota.creado')->exists());

        $this->actingAs($this->administrador)->put(route('caja.medios-pago.planes.update', [$credito, $plan]), [
            'cuotas' => '3',
            'recargo_pct' => '15',
            'activo' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('15.00', $plan->fresh()->recargo_pct);
        $this->assertTrue(Auditoria::where('accion', 'caja.planes_cuota.actualizado')->exists());
    }

    public function test_repeated_installments_are_rejected(): void
    {
        $credito = MedioPago::factory()->tipo(TipoMedioPago::TarjetaCredito)->create();
        PlanCuota::factory()->for($credito, 'medioPago')->create(['cuotas' => 3]);

        $this->actingAs($this->administrador)->post(route('caja.medios-pago.planes.store', $credito), [
            'cuotas' => '3',
            'recargo_pct' => '10',
        ])->assertSessionHasErrors('cuotas');

        $this->assertSame(1, $credito->planes()->count());
    }

    public function test_plans_are_only_for_credit_cards(): void
    {
        $efectivo = MedioPago::factory()->efectivo()->create();

        $this->actingAs($this->administrador)->post(route('caja.medios-pago.planes.store', $efectivo), [
            'cuotas' => '3',
            'recargo_pct' => '10',
        ])->assertNotFound();

        $this->assertSame(0, PlanCuota::count());
    }

    public function test_a_plan_is_edited_only_through_its_payment_method(): void
    {
        $credito = MedioPago::factory()->tipo(TipoMedioPago::TarjetaCredito)->create();
        $otro = MedioPago::factory()->tipo(TipoMedioPago::TarjetaCredito)->create();
        $plan = PlanCuota::factory()->for($otro, 'medioPago')->create();

        $this->actingAs($this->administrador)->get(route('caja.medios-pago.planes.edit', [$credito, $plan]))->assertNotFound();
    }

    public function test_a_credit_card_with_plans_cannot_change_its_type(): void
    {
        $credito = MedioPago::factory()->tipo(TipoMedioPago::TarjetaCredito)->create(['nombre' => 'Visa']);
        PlanCuota::factory()->for($credito, 'medioPago')->create();

        $this->actingAs($this->administrador)->put(route('caja.medios-pago.update', $credito), [
            'nombre' => 'Visa',
            'tipo' => TipoMedioPago::TarjetaDebito->value,
            'dias_acreditacion' => '0',
            'activo' => '1',
        ])->assertSessionHasErrors('tipo');
    }

    public function test_cash_registers_of_the_active_branch(): void
    {
        $ajena = Caja::factory()->create(['nombre' => 'Caja ajena']);

        $this->actingAs($this->administrador)->post(route('caja.cajas.store'), ['nombre' => 'Caja 2'])
            ->assertRedirect(route('caja.cajas.index'));

        $caja = Caja::where('nombre', 'Caja 2')->sole();
        $this->assertSame($this->sucursal->id, $caja->sucursal_id);
        $this->assertTrue($caja->activa);
        $this->assertTrue(Auditoria::where('accion', 'caja.cajas.creada')->exists());

        $this->actingAs($this->administrador)->post(route('caja.cajas.store'), ['nombre' => 'Caja 2'])
            ->assertSessionHasErrors('nombre');

        $this->actingAs($this->administrador)->get(route('caja.cajas.index'))
            ->assertOk()
            ->assertSee('Caja 2')
            ->assertDontSee('Caja ajena');

        $this->actingAs($this->administrador)->get(route('caja.cajas.edit', $ajena))->assertNotFound();
        $this->actingAs($this->administrador)->put(route('caja.cajas.update', $ajena), ['nombre' => 'X'])->assertNotFound();
    }

    public function test_the_same_cash_register_name_can_exist_in_another_branch(): void
    {
        Caja::factory()->create(['nombre' => Caja::PRINCIPAL]);

        $this->actingAs($this->administrador)->post(route('caja.cajas.store'), ['nombre' => Caja::PRINCIPAL])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Caja::where('nombre', Caja::PRINCIPAL)->count());
    }

    public function test_manager_cannot_configure(): void
    {
        $gerente = User::factory()->conSucursal($this->sucursal, Rol::Gerente)->create();
        $medio = MedioPago::factory()->create();

        $this->actingAs($gerente)->get(route('caja.medios-pago.index'))->assertForbidden();
        $this->actingAs($gerente)->get(route('caja.cajas.index'))->assertForbidden();
        $this->actingAs($gerente)->post(route('caja.medios-pago.store'), [
            'nombre' => 'Nuevo',
            'tipo' => TipoMedioPago::Qr->value,
            'dias_acreditacion' => '0',
        ])->assertForbidden();
        $this->actingAs($gerente)->put(route('caja.medios-pago.update', $medio), [
            'nombre' => 'Cambio',
            'tipo' => TipoMedioPago::Qr->value,
            'dias_acreditacion' => '0',
        ])->assertForbidden();
        $this->actingAs($gerente)->post(route('caja.cajas.store'), ['nombre' => 'Caja 3'])->assertForbidden();

        $this->assertSame(1, MedioPago::count());
        $this->assertSame(0, Caja::count());
    }
}
