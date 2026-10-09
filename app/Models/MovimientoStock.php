<?php

namespace App\Models;

use App\Enums\TipoMovimientoStock;
use App\Services\MovimientosStock;
use Database\Factories\MovimientoStockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Movimiento del kardex. Es inmutable: se crea con {@see MovimientosStock}
 * y un error se corrige con otro movimiento.
 */
class MovimientoStock extends Model
{
    /** @use HasFactory<MovimientoStockFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'movimientos_stock';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'variante_id',
        'sucursal_id',
        'fecha',
        'tipo',
        'cantidad',
        'saldo',
        'motivo',
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
            'tipo' => TipoMovimientoStock::class,
            'cantidad' => 'integer',
            'saldo' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos de stock no se pueden modificar.'));
        static::deleting(fn () => throw new LogicException('Los movimientos de stock no se pueden borrar.'));
    }

    /**
     * @return BelongsTo<Variante, $this>
     */
    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
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
     * Operación que originó el movimiento (venta, recepción, devolución…).
     *
     * @return MorphTo<Model, $this>
     */
    public function origen(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<MovimientoStock, $this>
     */
    public function reversaDe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversa_de_id');
    }
}
