<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Arqueo de un medio de pago al cerrar una sesión: esperado, contado y diferencia
 * (contado − esperado). Contado y diferencia son null en los medios que no impactan
 * en la caja física. Es inmutable.
 */
class CajaSesionArqueo extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'caja_sesion_arqueos';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'caja_sesion_id',
        'medio_pago_id',
        'esperado',
        'contado',
        'diferencia',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'esperado' => 'decimal:2',
            'contado' => 'decimal:2',
            'diferencia' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los arqueos de caja no se pueden modificar.'));
        static::deleting(fn () => throw new LogicException('Los arqueos de caja no se pueden borrar.'));
    }

    /**
     * @return BelongsTo<CajaSesion, $this>
     */
    public function sesion(): BelongsTo
    {
        return $this->belongsTo(CajaSesion::class, 'caja_sesion_id');
    }

    /**
     * @return BelongsTo<MedioPago, $this>
     */
    public function medioPago(): BelongsTo
    {
        return $this->belongsTo(MedioPago::class);
    }
}
