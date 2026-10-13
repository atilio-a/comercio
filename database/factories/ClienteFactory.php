<?php

namespace Database\Factories;

use App\Enums\CondicionIva;
use App\Models\Cliente;
use App\Services\Cuit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'dni_cuit' => fake()->unique()->numerify('3#######'),
            'telefono' => fake()->numerify('388 4######'),
            'email' => fake()->unique()->safeEmail(),
            'talle_habitual' => null,
            'condicion_iva' => CondicionIva::ConsumidorFinal,
            'observaciones' => null,
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }

    public function sinDocumento(): static
    {
        return $this->state(fn () => ['dni_cuit' => null]);
    }

    /**
     * Responsable inscripto con CUIT de persona válido.
     */
    public function responsableInscripto(): static
    {
        return $this->state(fn () => [
            'condicion_iva' => CondicionIva::ResponsableInscripto,
            'dni_cuit' => self::cuitValido(),
        ]);
    }

    /**
     * CUIT de persona al azar con dígito verificador correcto.
     */
    public static function cuitValido(): string
    {
        do {
            $base = '20'.fake()->unique()->numerify('########');
            $digito = Cuit::digitoVerificador($base);
        } while ($digito === null);

        return $base.$digito;
    }
}
