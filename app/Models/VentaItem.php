<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Ítem de una venta. Guarda la foto del momento: precio final y neto unitarios, alícuota y
 * costo; y los importes de la línea: descuento propio, parte del descuento general, parte
 * del recargo por cuotas, total gravado y su desglose (`neto + iva = total`). Inmutable.
 */
class VentaItem extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'venta_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'venta_id',
        'variante_id',
        'cantidad',
        'precio_final',
        'precio_neto',
        'alicuota',
        'descuento',
        'descuento_autorizado_por',
        'descuento_venta',
        'recargo',
        'total',
        'neto',
        'iva',
        'promocion_id',
        'costo_unitario',
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
            'precio_final' => 'decimal:2',
            'precio_neto' => 'decimal:2',
            'alicuota' => 'decimal:2',
            'descuento' => 'decimal:2',
            'descuento_venta' => 'decimal:2',
            'recargo' => 'decimal:2',
            'total' => 'decimal:2',
            'neto' => 'decimal:2',
            'iva' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los ítems de una venta no se pueden modificar.'));
        static::deleting(fn () => throw new LogicException('Los ítems de una venta no se pueden borrar.'));
    }

    /**
     * @return BelongsTo<Venta, $this>
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * @return BelongsTo<Variante, $this>
     */
    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }

    /**
     * Quien autorizó el descuento del ítem.
     *
     * @return BelongsTo<User, $this>
     */
    public function autorizanteDescuento(): BelongsTo
    {
        return $this->belongsTo(User::class, 'descuento_autorizado_por');
    }
}
