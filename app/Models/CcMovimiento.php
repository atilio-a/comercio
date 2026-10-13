<?php

namespace App\Models;

use App\Enums\TipoMovimientoCuenta;
use App\Services\CuentasCorrientes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Movimiento de cuenta corriente. Es inmutable: se crea con {@see CuentasCorrientes} y un
 * error se corrige con un contraasiento. `importe` es siempre positivo y `tipo` da el sentido;
 * `saldo` es el saldo de la cuenta después del movimiento (positivo = debe, negativo = a favor).
 */
class CcMovimiento extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'cc_movimientos';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cuenta_id',
        'sucursal_id',
        'fecha',
        'tipo',
        'importe',
        'saldo',
        'concepto',
        'origen_type',
        'origen_id',
        'reversa_de_id',
        'vencimiento',
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
            'tipo' => TipoMovimientoCuenta::class,
            'importe' => 'decimal:2',
            'saldo' => 'decimal:2',
            'vencimiento' => 'date',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos de cuenta corriente no se pueden modificar.'));
        static::deleting(fn () => throw new LogicException('Los movimientos de cuenta corriente no se pueden borrar.'));
    }

    public function esContraasiento(): bool
    {
        return $this->reversa_de_id !== null;
    }

    /**
     * @return BelongsTo<CuentaCorriente, $this>
     */
    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaCorriente::class, 'cuenta_id');
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Operación que originó el movimiento (devolución, venta, cobranza…).
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
     * @return BelongsTo<CcMovimiento, $this>
     */
    public function reversaDe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversa_de_id');
    }

    /**
     * Contraasiento que revirtió este movimiento, si lo hay.
     *
     * @return HasOne<CcMovimiento, $this>
     */
    public function contraasiento(): HasOne
    {
        return $this->hasOne(self::class, 'reversa_de_id');
    }
}
