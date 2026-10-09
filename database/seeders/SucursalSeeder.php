<?php

namespace Database\Seeders;

use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class SucursalSeeder extends Seeder
{
    /**
     * Crea la sucursal inicial si no existe.
     */
    public function run(): void
    {
        Sucursal::firstOrCreate(
            ['nombre' => Sucursal::PRINCIPAL],
            ['activa' => true],
        );
    }
}
