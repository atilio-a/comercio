<?php

namespace App\Models;

use App\Enums\TipoMovimientoCaja;
use App\Services\MovimientosCaja;
use Database\Factories\CajaMovimientoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Movimiento de dinero en una sesión de caja (CAJ-05). Es inmutable: se crea con
 * {@see MovimientosCaja} y un error se corrige con un contraasiento.
 */
class CajaMovimiento extends Model
{
    /** @use HasFactory<CajaMovimientoFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'caja_movimientos';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'caja_sesion_id',
        'sucursal_id',
        'fecha',
        'tipo',
        'medio_pago_id',
        'importe',
        'motivo',
        'referencia',
        'origen_type',
        'origen_id',
        'reversa_de_id',
        'user_id',
        'dispositivo',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'tipo' => TipoMovimientoCaja::class,
            'importe' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos de caja no se pueden modificar.'));
        static::deleting(fn () => throw new LogicException('Los movimientos de caja no se pueden borrar.'));
    }

    public function esContraasiento(): bool
    {
        return $this->reversa_de_id !== null;
    }

    /**
     * @return BelongsTo<CajaSesion, $this>
     */
    public function sesion(): BelongsTo
    {
        return $this->belongsTo(CajaSesion::class, 'caja_sesion_id');
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * @return BelongsTo<MedioPago, $this>
     */
    public function medioPago(): BelongsTo
    {
        return $this->belongsTo(MedioPago::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Operación que originó el movimiento (venta, cobranza, pago…).
     *
     * @return MorphTo<Model, $this>
     */
    public function origen(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Movimiento que este contraasiento revierte.
     *
     * @return BelongsTo<CajaMovimiento, $this>
     */
    public function reversaDe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversa_de_id');
    }

    /**
     * Contraasiento que revirtió este movimiento, si lo hay.
     *
     * @return HasOne<CajaMovimiento, $this>
     */
    public function contraasiento(): HasOne
    {
        return $this->hasOne(self::class, 'reversa_de_id');
    }
}
