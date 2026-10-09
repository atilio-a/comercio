<?php

namespace Database\Seeders;

use App\Models\AlicuotaIva;
use Illuminate\Database\Seeder;

class AlicuotaIvaSeeder extends Seeder
{
    /**
     * Alícuotas de IVA vigentes, sin pisar las existentes.
     */
    public function run(): void
    {
        $alicuotas = [
            'IVA 21 %' => '21.00',
            'IVA 10,5 %' => '10.50',
            'IVA 27 %' => '27.00',
            'IVA 0 %' => '0.00',
        ];

        foreach ($alicuotas as $nombre => $porcentaje) {
            AlicuotaIva::firstOrCreate(['nombre' => $nombre], ['porcentaje' => $porcentaje, 'activo' => true]);
        }
    }
}
