<?php

namespace Database\Factories;

use App\Models\Recepcion;
use App\Models\RecepcionItem;
use App\Models\Variante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecepcionItem>
 */
class RecepcionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recepcion_id' => Recepcion::factory(),
            'variante_id' => Variante::factory(),
            'orden_compra_item_id' => null,
            'cantidad' => 1,
            'costo' => '1000.00',
            'actualiza_costo' => false,
        ];
    }
}
