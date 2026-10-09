<?php

namespace Database\Factories;

use App\Models\Marca;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Marca>
 */
class MarcaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->company(),
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
