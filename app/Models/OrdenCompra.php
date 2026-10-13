<?php

namespace App\Models;

use App\Enums\EstadoOrdenCompra;
use App\Services\Importes;
use App\Services\OrdenesCompra;
use Database\Factories\OrdenCompraFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Orden de compra a un proveedor (COM-01). Se crea y modifica con {@see OrdenesCompra};
 * no se borra: se anula.
 */
class OrdenCompra extends Model
{
    /** @use HasFactory<OrdenCompraFactory> */
    use HasFactory;

    protected $table = 'ordenes_compra';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sucursal_id',
        'proveedor_id',
        'numero',
        'fecha',
        'estado',
        'observaciones',
        'user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'fecha' => 'date',
            'estado' => EstadoOrdenCompra::class,
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Las órdenes de compra no se borran: se anulan.'));
    }

    /**
     * Estado que corresponde según lo recibido en los ítems: todo → recibida,
     * algo → parcial, nada → emitida.
     */
    public function estadoSegunRecepciones(): EstadoOrdenCompra
    {
        if ($this->items->sum('cantidad_recibida') === 0) {
            return EstadoOrdenCompra::Emitida;
        }

        return $this->items->every(fn (OrdenCompraItem $item) => $item->pendiente() === 0)
            ? EstadoOrdenCompra::Recibida
            : EstadoOrdenCompra::Parcial;
    }

    /**
     * Total pedido (cantidad × costo) en centavos.
     */
    public function totalCentavos(): int
    {
        return $this->items->sum(fn (OrdenCompraItem $item) => $item->cantidad_pedida * Importes::aCentavos($item->costo));
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrdenCompraItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrdenCompraItem::class);
    }

    /**
     * @return HasMany<Recepcion, $this>
     */
    public function recepciones(): HasMany
    {
        return $this->hasMany(Recepcion::class);
    }
}
