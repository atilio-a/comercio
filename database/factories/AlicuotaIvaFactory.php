<?php

namespace Database\Factories;

use App\Models\AlicuotaIva;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlicuotaIva>
 */
class AlicuotaIvaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'IVA '.fake()->unique()->numberBetween(1, 99).' %',
            'porcentaje' => '21.00',
            'activo' => true,
        ];
    }

    /**
     * Registro desactivado.
     */
    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
