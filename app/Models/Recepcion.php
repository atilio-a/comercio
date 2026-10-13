<?php

namespace App\Models;

use App\Services\RecepcionesCompra;
use Database\Factories\RecepcionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

/**
 * Recepción de mercadería (COM-02). Es inmutable: se crea con {@see RecepcionesCompra}
 * y la única modificación admitida es su anulación, una sola vez.
 */
class Recepcion extends Model
{
    /** @use HasFactory<RecepcionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Columnas que se completan al anular: las únicas que pueden cambiar.
     */
    private const CAMPOS_ANULACION = ['anulada_at', 'anulada_por_id', 'motivo_anulacion'];

    protected $table = 'recepciones';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sucursal_id',
        'proveedor_id',
        'orden_compra_id',
        'fecha',
        'referencia',
        'observaciones',
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
            'anulada_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Recepcion $recepcion) {
            $soloAnulacion = array_diff(array_keys($recepcion->getDirty()), self::CAMPOS_ANULACION) === [];

            if (! $soloAnulacion || $recepcion->getOriginal('anulada_at') !== null) {
                throw new LogicException('Las recepciones no se pueden modificar: se anulan.');
            }
        });
        static::deleting(fn () => throw new LogicException('Las recepciones no se pueden borrar.'));
    }

    public function estaAnulada(): bool
    {
        return $this->anulada_at !== null;
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * @return BelongsTo<Proveedor, $this>
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /**
     * @return BelongsTo<OrdenCompra, $this>
     */
    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulada_por_id');
    }

    /**
     * @return HasMany<RecepcionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RecepcionItem::class);
    }

    /**
     * Movimientos de stock generados por la recepción y por su anulación.
     *
     * @return MorphMany<MovimientoStock, $this>
     */
    public function movimientosStock(): MorphMany
    {
        return $this->morphMany(MovimientoStock::class, 'origen');
    }
}
