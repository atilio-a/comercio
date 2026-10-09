<?php

namespace Database\Factories;

use App\Enums\TipoMovimientoStock;
use App\Models\MovimientoStock;
use App\Models\Sucursal;
use App\Models\Variante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo para tests: en la aplicación los movimientos se crean con MovimientosStock.
 *
 * @extends Factory<MovimientoStock>
 */
class MovimientoStockFactory extends Factory
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
            'fecha' => now()->toDateString(),
            'tipo' => TipoMovimientoStock::Ajuste,
            'cantidad' => 1,
            'saldo' => 1,
            'motivo' => 'Prueba',
        ];
    }
}
