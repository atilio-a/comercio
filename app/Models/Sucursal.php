<?php

namespace App\Models;

use Database\Factories\SucursalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Sucursal extends Model
{
    /** @use HasFactory<SucursalFactory> */
    use HasFactory;

    /**
     * Nombre de la sucursal creada en la instalación.
     */
    public const PRINCIPAL = 'Local principal';

    protected $table = 'sucursales';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'domicilio',
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
     * Usuarios asignados a la sucursal.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Solo sucursales activas.
     *
     * @param  Builder<Sucursal>  $query
     */
    public function scopeActivas(Builder $query): void
    {
        $query->where('activa', true);
    }
}
