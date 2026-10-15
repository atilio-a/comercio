<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Ítem de una devolución: unidades devueltas de un ítem de la venta y la parte de lo cobrado
 * por él que se devuelve, con su desglose (`neto + iva = importe`). Inmutable.
 */
class DevolucionItem extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'devolucion_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'devolucion_id',
        'venta_item_id',
        'variante_id',
        'cantidad',
        'importe',
        'neto',
        'iva',
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
            'importe' => 'decimal:2',
            'neto' => 'decimal:2',
            'iva' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los ítems de una devolución no se pueden modificar.'));
        static::deleting(fn () => throw new LogicException('Los ítems de una devolución no se pueden borrar.'));
    }

    /**
     * @return BelongsTo<Devolucion, $this>
     */
    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class);
    }

    /**
     * @return BelongsTo<VentaItem, $this>
     */
    public function ventaItem(): BelongsTo
    {
        return $this->belongsTo(VentaItem::class);
    }

    /**
     * @return BelongsTo<Variante, $this>
     */
    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }
}
