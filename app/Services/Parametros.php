<?php

namespace App\Services;

use App\Enums\Parametro;
use App\Models\Parametro as ParametroGuardado;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Lectura y escritura de los parámetros del sistema, con caché.
 */
class Parametros
{
    public const CLAVE_CACHE = 'parametros.valores';

    /**
     * Valor actual del parámetro; si no está guardado, su valor por defecto.
     */
    public function habilitado(Parametro $parametro): bool
    {
        $valores = $this->valoresGuardados();

        if (! array_key_exists($parametro->value, $valores)) {
            return $parametro->valorPorDefecto();
        }

        return (bool) $valores[$parametro->value];
    }

    /**
     * Valores actuales de todos los parámetros.
     *
     * @return array<string, bool>
     */
    public function todos(): array
    {
        $valores = [];

        foreach (Parametro::cases() as $parametro) {
            $valores[$parametro->value] = $this->habilitado($parametro);
        }

        return $valores;
    }

    /**
     * Guarda un valor y devuelve el anterior. No audita: eso lo hace quien llama,
     * dentro de su transacción.
     */
    public function guardar(Parametro $parametro, bool $valor): bool
    {
        $anterior = $this->habilitado($parametro);

        ParametroGuardado::updateOrCreate(
            ['clave' => $parametro->value],
            ['valor' => $valor ? '1' : '0'],
        );

        // Inside a transaction the cache could be rebuilt with uncommitted values,
        // so it is cleared again when the transaction ends either way.
        $this->olvidarCache();
        DB::afterCommit(fn () => $this->olvidarCache());
        DB::afterRollBack(fn () => $this->olvidarCache());

        return $anterior;
    }

    public function olvidarCache(): void
    {
        Cache::forget(self::CLAVE_CACHE);
    }

    /**
     * @return array<string, string|null>
     */
    private function valoresGuardados(): array
    {
        return Cache::rememberForever(
            self::CLAVE_CACHE,
            fn (): array => ParametroGuardado::query()->pluck('valor', 'clave')->all(),
        );
    }
}
