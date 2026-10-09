<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\GestionUsuarios;
use App\Services\SucursalActiva;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CrearAdministrador extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'comercio:crear-administrador';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un usuario Administrador en la sucursal principal';

    /**
     * Pide los datos de forma interactiva para no dejar credenciales en el código ni en el historial.
     */
    public function handle(GestionUsuarios $gestion, SucursalActiva $sucursalActiva): int
    {
        $principal = Sucursal::where('nombre', Sucursal::PRINCIPAL)->first();

        if (! $principal || ! Role::where('name', Rol::Administrador->value)->exists()) {
            $this->components->error('Faltan los datos iniciales. Ejecutá primero: php artisan db:seed');

            return self::FAILURE;
        }

        $nombre = text(
            label: 'Nombre',
            required: true,
            validate: fn (string $valor) => $this->validar('name', $valor, ['max:255']),
        );

        $email = text(
            label: 'Email',
            required: true,
            validate: fn (string $valor) => $this->validar('email', $valor, ['email', 'max:255', Rule::unique(User::class)]),
        );

        $clave = password(
            label: 'Contraseña',
            required: true,
            validate: fn (string $valor) => $this->validar('password', $valor, [Password::defaults()]),
        );

        $confirmacion = password(label: 'Repetir contraseña', required: true);

        if ($clave !== $confirmacion) {
            $this->components->error('Las contraseñas no coinciden.');

            return self::FAILURE;
        }

        $sucursalActiva->establecer($principal);

        $user = $gestion->crear(
            ['name' => $nombre, 'email' => strtolower($email), 'password' => $clave, 'activo' => true],
            [$principal->id => Rol::Administrador],
        );

        $this->components->info("Administrador {$user->email} creado en \"{$principal->nombre}\".");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, mixed>  $reglas
     */
    private function validar(string $campo, string $valor, array $reglas): ?string
    {
        $validator = Validator::make([$campo => $valor], [$campo => array_merge(['required'], $reglas)]);

        return $validator->fails() ? $validator->errors()->first($campo) : null;
    }
}
