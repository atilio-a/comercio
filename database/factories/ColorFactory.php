<?php

namespace Database\Factories;

use App\Models\Color;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Color>
 */
class ColorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->colorName(),
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
