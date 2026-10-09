<?php

namespace Database\Seeders;

use App\Enums\Parametro;
use App\Models\Parametro as ParametroGuardado;
use App\Services\Parametros;
use Illuminate\Database\Seeder;

class ParametroSeeder extends Seeder
{
    /**
     * Crea los parámetros que falten con su valor por defecto, sin pisar los ya guardados.
     */
    public function run(Parametros $parametros): void
    {
        foreach (Parametro::cases() as $parametro) {
            ParametroGuardado::firstOrCreate(
                ['clave' => $parametro->value],
                ['valor' => $parametro->valorPorDefecto() ? '1' : '0'],
            );
        }

        $parametros->olvidarCache();
    }
}
