<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Los seeders son idempotentes: se pueden volver a ejecutar sin duplicar datos
     * ni pisar valores elegidos por el Administrador. No crean usuarios: el primer
     * Administrador se crea con `php artisan comercio:crear-administrador`.
     */
    public function run(): void
    {
        $this->call([
            SucursalSeeder::class,
            RolesYPermisosSeeder::class,
            ParametroSeeder::class,
            AlicuotaIvaSeeder::class,
            ColorSeeder::class,
        ]);
    }
}
