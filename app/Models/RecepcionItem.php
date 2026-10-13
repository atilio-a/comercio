<?php

namespace App\Models;

use Database\Factories\RecepcionItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Ítem recibido de una variante. Inmutable, como la recepción.
 */
class RecepcionItem extends Model
{
    /** @use HasFactory<RecepcionItemFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'recepcion_id',
        'variante_id',
        'orden_compra_item_id',
        'cantidad',
        'costo',
        'actualiza_costo',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'costo' => 'decimal:2',
            'actualiza_costo' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los ítems de una recepción no se pueden modificar.'));
        static::deleting(fn () => throw new LogicException('Los ítems de una recepción no se pueden borrar.'));
    }

    /**
     * @return BelongsTo<Recepcion, $this>
     */
    public function recepcion(): BelongsTo
    {
        return $this->belongsTo(Recepcion::class);
    }

    /**
     * @return BelongsTo<Variante, $this>
     */
    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }

    /**
     * @return BelongsTo<OrdenCompraItem, $this>
     */
    public function ordenItem(): BelongsTo
    {
        return $this->belongsTo(OrdenCompraItem::class, 'orden_compra_item_id');
    }
}
