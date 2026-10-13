<?php

namespace Database\Factories;

use App\Enums\EstadoOrdenCompra;
use App\Models\OrdenCompra;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo para tests: en la aplicación las órdenes se crean con OrdenesCompra.
 *
 * @extends Factory<OrdenCompra>
 */
class OrdenCompraFactory extends Factory
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
            'numero' => fn (array $atributos) => (int) OrdenCompra::query()->where('sucursal_id', $atributos['sucursal_id'])->max('numero') + 1,
            'fecha' => now()->toDateString(),
            'estado' => EstadoOrdenCompra::Borrador,
            'observaciones' => null,
            'user_id' => User::factory(),
        ];
    }

    public function emitida(): static
    {
        return $this->state(fn () => ['estado' => EstadoOrdenCompra::Emitida]);
    }
}
