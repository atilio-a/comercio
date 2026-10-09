<?php

namespace Database\Factories;

use App\Models\Color;
use App\Models\Producto;
use App\Models\Variante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Variante>
 */
class VarianteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'color_id' => Color::factory(),
            'talle' => null,
            'sku' => strtoupper(fake()->unique()->bothify('SKU-#####??')),
            'codigo_barras' => fake()->unique()->ean13(),
            'costo' => '1000.00',
            'precio_final' => '2000.00',
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
