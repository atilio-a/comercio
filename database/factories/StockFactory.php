<?php

namespace Database\Factories;

use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\Variante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo para tests: en la aplicación el stock se modifica con MovimientosStock.
 *
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'variante_id' => Variante::factory(),
            'sucursal_id' => Sucursal::factory(),
            'cantidad' => 0,
            'minimo' => 0,
        ];
    }
}
