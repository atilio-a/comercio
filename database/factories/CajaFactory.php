<?php

namespace Database\Factories;

use App\Models\Caja;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Caja>
 */
class CajaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sucursal_id' => Sucursal::factory(),
            'nombre' => 'Caja '.fake()->unique()->numberBetween(1, 99999),
            'activa' => true,
        ];
    }

    /**
     * Caja deshabilitada.
     */
    public function inactiva(): static
    {
        return $this->state(fn () => ['activa' => false]);
    }
}
