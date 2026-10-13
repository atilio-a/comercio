<?php

namespace Database\Factories;

use App\Models\Proveedor;
use App\Services\Cuit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'razon_social' => fake()->unique()->company(),
            'cuit' => self::cuitValido(),
            'contacto' => fake()->name(),
            'telefono' => fake()->numerify('388 4######'),
            'email' => fake()->unique()->safeEmail(),
            'condiciones_pago' => '30 días',
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }

    public function sinCuit(): static
    {
        return $this->state(fn () => ['cuit' => null]);
    }

    /**
     * CUIT de empresa al azar con dígito verificador correcto.
     */
    public static function cuitValido(): string
    {
        do {
            $base = '30'.fake()->unique()->numerify('########');
            $digito = Cuit::digitoVerificador($base);
        } while ($digito === null);

        return $base.$digito;
    }
}
