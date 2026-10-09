<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'activo' => true,
        ];
    }

    /**
     * Asigna el usuario a una sucursal (por defecto, "Local principal") y, opcionalmente,
     * le da un rol en ella.
     */
    public function conSucursal(?Sucursal $sucursal = null, ?Rol $rol = null): static
    {
        return $this->afterCreating(function (User $user) use ($sucursal, $rol) {
            $sucursal ??= Sucursal::firstOrCreate(['nombre' => Sucursal::PRINCIPAL]);

            $user->sucursales()->attach($sucursal);

            if ($rol) {
                $user->asignarRol($rol, $sucursal);
            }
        });
    }

    /**
     * Usuario deshabilitado.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
