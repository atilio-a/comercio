<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\CuentaCorriente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo para tests: en la aplicación las cuentas se crean con CuentasCorrientes::cuentaDe().
 *
 * @extends Factory<CuentaCorriente>
 */
class CuentaCorrienteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'admite_credito' => false,
            'limite_credito' => '0.00',
            'bloqueada' => false,
            'saldo' => '0.00',
        ];
    }

    /**
     * Cuenta con crédito habilitado hasta el límite indicado.
     */
    public function conCredito(string $limite = '50000.00'): static
    {
        return $this->state(fn () => ['admite_credito' => true, 'limite_credito' => $limite]);
    }

    public function bloqueada(): static
    {
        return $this->state(fn () => ['bloqueada' => true]);
    }
}
