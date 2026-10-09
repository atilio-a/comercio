<?php

namespace App\Models;

use App\Services\RegistradorAuditoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Registro de auditoría. Es inmutable: se crea con {@see RegistradorAuditoria}
 * y no se edita ni se borra.
 */
class Auditoria extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'auditorias';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'sucursal_id',
        'accion',
        'modelo',
        'modelo_id',
        'antes',
        'despues',
        'ip',
        'dispositivo',
        'user_agent',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'antes' => 'array',
            'despues' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los registros de auditoría no se pueden modificar.'));
        static::deleting(fn () => throw new LogicException('Los registros de auditoría no se pueden borrar.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Registros de una sucursal.
     *
     * @param  Builder<Auditoria>  $query
     */
    public function scopeDeSucursal(Builder $query, int $sucursalId): void
    {
        $query->where('sucursal_id', $sucursalId);
    }
}
