<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Valor guardado de un parámetro del sistema. La definición (tipo, valor por
 * defecto, etiqueta y ayuda) vive en {@see \App\Enums\Parametro}.
 */
class Parametro extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'clave',
        'valor',
    ];
}
