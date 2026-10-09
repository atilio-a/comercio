<?php

namespace Database\Factories;

use App\Enums\TipoMedioPago;
use App\Models\MedioPago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedioPago>
 */
class MedioPagoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Medio '.fake()->unique()->numberBetween(1, 99999),
            'tipo' => TipoMedioPago::Transferencia,
            'impacta_caja' => false,
            'dias_acreditacion' => 0,
            'requiere_referencia' => false,
            'activo' => true,
        ];
    }

    /**
     * Efectivo: impacta en la caja física.
     */
    public function efectivo(): static
    {
        return $this->state(fn () => [
            'nombre' => 'Efectivo '.fake()->unique()->numberBetween(1, 99999),
            'tipo' => TipoMedioPago::Efectivo,
            'impacta_caja' => true,
        ]);
    }

    /**
     * De un tipo dado.
     */
    public function tipo(TipoMedioPago $tipo): static
    {
        return $this->state(fn () => ['tipo' => $tipo]);
    }

    /**
     * Registro desactivado.
     */
    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
