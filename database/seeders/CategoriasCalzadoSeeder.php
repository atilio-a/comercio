<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

/**
 * Datos de ejemplo para un comercio de calzado. No forma parte de los datos iniciales:
 * se ejecuta a mano con `php artisan db:seed --class=CategoriasCalzadoSeeder`.
 */
class CategoriasCalzadoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Botas', 'Sandalias', 'Zapatos', 'Zapatillas'] as $nombre) {
            Categoria::firstOrCreate(['nombre' => $nombre], ['activo' => true]);
        }
    }
}
