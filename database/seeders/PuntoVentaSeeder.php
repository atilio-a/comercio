<?php

namespace Database\Seeders;

use App\Models\PuntoVenta;
use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class PuntoVentaSeeder extends Seeder
{
    /**
     * Crea un punto de venta activo en cada sucursal activa que no tenga ninguno, con el menor
     * número libre (el 1 en una instalación con una sola sucursal). El número es único en todo
     * el sistema.
     */
    public function run(): void
    {
        foreach (Sucursal::activas()->orderBy('id')->get() as $sucursal) {
            if (PuntoVenta::query()->where('sucursal_id', $sucursal->id)->exists()) {
                continue;
            }

            PuntoVenta::create([
                'sucursal_id' => $sucursal->id,
                'numero' => $this->menorNumeroLibre(),
                'descripcion' => 'Mostrador',
                'activo' => true,
            ]);
        }
    }

    private function menorNumeroLibre(): int
    {
        $usados = PuntoVenta::query()->orderBy('numero')->pluck('numero')->all();
        $numero = 1;

        foreach ($usados as $usado) {
            if ($usado > $numero) {
                break;
            }

            $numero = $usado + 1;
        }

        return $numero;
    }
}
