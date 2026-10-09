<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class CajaSeeder extends Seeder
{
    /**
     * Crea "Caja principal" en cada sucursal activa que no la tenga.
     */
    public function run(): void
    {
        foreach (Sucursal::activas()->orderBy('id')->get() as $sucursal) {
            Caja::firstOrCreate(
                ['sucursal_id' => $sucursal->id, 'nombre' => Caja::PRINCIPAL],
                ['activa' => true],
            );
        }
    }
}
