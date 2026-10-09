<?php

namespace Database\Factories;

use App\Enums\TipoMedioPago;
use App\Models\MedioPago;
use App\Models\PlanCuota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanCuota>
 */
class PlanCuotaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'medio_pago_id' => MedioPago::factory()->tipo(TipoMedioPago::TarjetaCredito),
            'cuotas' => fake()->unique()->numberBetween(1, 60),
            'recargo_pct' => '0.00',
            'activo' => true,
        ];
    }
}
