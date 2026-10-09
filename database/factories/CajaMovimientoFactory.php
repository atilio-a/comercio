<?php

namespace Database\Factories;

use App\Enums\TipoMovimientoCaja;
use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Models\MedioPago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo para tests: en la aplicación los movimientos se crean con MovimientosCaja.
 *
 * @extends Factory<CajaMovimiento>
 */
class CajaMovimientoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'caja_sesion_id' => CajaSesion::factory(),
            'sucursal_id' => fn (array $atributos) => CajaSesion::find($atributos['caja_sesion_id'])->sucursal_id,
            'fecha' => now()->toDateString(),
            'tipo' => TipoMovimientoCaja::Ingreso,
            'medio_pago_id' => MedioPago::factory()->efectivo(),
            'importe' => '100.00',
            'motivo' => 'Prueba',
        ];
    }
}
