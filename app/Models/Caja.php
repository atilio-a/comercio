<?php

namespace App\Models;

use App\Enums\EstadoSesionCaja;
use Database\Factories\CajaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Caja de una sucursal. Una sucursal puede tener varias.
 */
class Caja extends Model
{
    /** @use HasFactory<CajaFactory> */
    use HasFactory;

    /**
     * Nombre de la caja que crean los datos iniciales en cada sucursal.
     */
    public const PRINCIPAL = 'Caja principal';

    protected $table = 'cajas';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sucursal_id',
        'nombre',
        'activa',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * @return HasMany<CajaSesion, $this>
     */
    public function sesiones(): HasMany
    {
        return $this->hasMany(CajaSesion::class);
    }

    /**
     * @return HasOne<CajaSesion, $this>
     */
    public function sesionAbierta(): HasOne
    {
        return $this->hasOne(CajaSesion::class)->where('estado', EstadoSesionCaja::Abierta->value);
    }

    /**
     * Cajas activas de una sucursal.
     *
     * @param  Builder<Caja>  $query
     */
    public function scopeActivasDe(Builder $query, int $sucursalId): void
    {
        $query->where('sucursal_id', $sucursalId)->where('activa', true);
    }
}
