<?php

namespace App\Models;

use App\Models\Concerns\TieneEstadoActivo;
use Database\Factories\ColorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Color de variantes (tabla maestra).
 */
class Color extends Model
{
    /** @use HasFactory<ColorFactory> */
    use HasFactory, TieneEstadoActivo;

    protected $table = 'colores';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'activo',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * Variantes de este color.
     *
     * @return HasMany<Variante, $this>
     */
    public function variantes(): HasMany
    {
        return $this->hasMany(Variante::class);
    }
}
