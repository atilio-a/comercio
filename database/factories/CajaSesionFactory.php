<?php

namespace Database\Factories;

use App\Enums\EstadoSesionCaja;
use App\Models\Caja;
use App\Models\CajaSesion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo para tests: en la aplicación las sesiones se abren con SesionesCaja.
 *
 * @extends Factory<CajaSesion>
 */
class CajaSesionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'caja_id' => Caja::factory(),
            'sucursal_id' => fn (array $atributos) => Caja::find($atributos['caja_id'])->sucursal_id,
            'user_id' => User::factory(),
            'fecha' => now()->toDateString(),
            'apertura' => now(),
            'fondo_inicial' => '0.00',
            'estado' => EstadoSesionCaja::Abierta,
        ];
    }

    /**
     * Sesión ya cerrada.
     */
    public function cerrada(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoSesionCaja::Cerrada,
            'cierre' => now(),
        ]);
    }
}
