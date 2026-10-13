<?php

namespace Database\Factories;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use App\Models\Variante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrdenCompraItem>
 */
class OrdenCompraItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'orden_compra_id' => OrdenCompra::factory(),
            'variante_id' => Variante::factory(),
            'cantidad_pedida' => 10,
            'cantidad_recibida' => 0,
            'costo' => '1000.00',
        ];
    }
}
