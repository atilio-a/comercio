<?php

namespace Database\Factories;

use App\Models\AlicuotaIva;
use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => ucfirst(fake()->words(2, true)),
            'categoria_id' => Categoria::factory(),
            'marca_id' => null,
            'temporada_id' => null,
            'alicuota_iva_id' => AlicuotaIva::factory(),
            'descripcion' => null,
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
