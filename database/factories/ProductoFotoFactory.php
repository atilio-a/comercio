<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\ProductoFoto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Solo registros: no crea archivos. Para tests con archivos usar GaleriaProducto.
 *
 * @extends Factory<ProductoFoto>
 */
class ProductoFotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = (string) Str::uuid();

        return [
            'producto_id' => Producto::factory(),
            'color_id' => null,
            'ruta' => "productos/0/{$nombre}.webp",
            'ruta_miniatura' => "productos/0/{$nombre}-min.webp",
            'es_principal' => false,
            'orden' => 1,
            'ancho' => 1600,
            'alto' => 1200,
            'bytes' => 300000,
        ];
    }

    public function principal(): static
    {
        return $this->state(fn () => ['es_principal' => true]);
    }
}
