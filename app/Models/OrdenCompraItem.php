<?php

namespace App\Models;

use Database\Factories\OrdenCompraItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ítem de una orden de compra: lo pedido de una variante, lo recibido y el costo pactado.
 */
class OrdenCompraItem extends Model
{
    /** @use HasFactory<OrdenCompraItemFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'orden_compra_id',
        'variante_id',
        'cantidad_pedida',
        'cantidad_recibida',
        'costo',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_pedida' => 'integer',
            'cantidad_recibida' => 'integer',
            'costo' => 'decimal:2',
        ];
    }

    /**
     * Cantidad que falta recibir (nunca negativa).
     */
    public function pendiente(): int
    {
        return max(0, $this->cantidad_pedida - $this->cantidad_recibida);
    }

    /**
     * @return BelongsTo<OrdenCompra, $this>
     */
    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }

    /**
     * @return BelongsTo<Variante, $this>
     */
    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }
}
