<?php

namespace Database\Factories;

use App\Models\Proveedor;
use App\Models\Recepcion;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo para tests: en la aplicación las recepciones se crean con RecepcionesCompra,
 * que además registra el stock.
 *
 * @extends Factory<Recepcion>
 */
class RecepcionFactory extends Factory
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
            'proveedor_id' => Proveedor::factory(),
            'orden_compra_id' => null,
            'fecha' => now()->toDateString(),
            'referencia' => 'R-'.fake()->numerify('0001-########'),
            'observaciones' => null,
            'user_id' => User::factory(),
            'dispositivo' => fake()->uuid(),
        ];
    }
}
