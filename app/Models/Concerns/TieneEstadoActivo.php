<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Registros que no se borran: se activan o desactivan con la columna `activo`.
 */
trait TieneEstadoActivo
{
    /**
     * Solo registros activos.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where($this->qualifyColumn('activo'), true);
    }

    /**
     * Activos, más el registro indicado aunque esté inactivo (para formularios de edición).
     *
     * @param  Builder<static>  $query
     */
    public function scopeActivosOIncluido(Builder $query, ?int $incluidoId): void
    {
        $query->where(function (Builder $query) use ($incluidoId) {
            $query->where($this->qualifyColumn('activo'), true);

            if ($incluidoId) {
                $query->orWhere($this->getQualifiedKeyName(), $incluidoId);
            }
        });
    }
}
