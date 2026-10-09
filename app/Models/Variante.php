<?php

namespace App\Models;

use App\Models\Concerns\TieneEstadoActivo;
use Database\Factories\VarianteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unidad de stock y de venta: un producto en un color y, si corresponde, un talle.
 * Color y talle la identifican y no se modifican después de crearla.
 */
class Variante extends Model
{
    /** @use HasFactory<VarianteFactory> */
    use HasFactory, TieneEstadoActivo;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'producto_id',
        'color_id',
        'talle',
        'sku',
        'codigo_barras',
        'costo',
        'precio_final',
        'activo',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'costo' => 'decimal:2',
            'precio_final' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * @return BelongsTo<Color, $this>
     */
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    /**
     * Variantes que se pueden vender: la variante y su producto están activos.
     *
     * @param  Builder<Variante>  $query
     */
    public function scopeVendibles(Builder $query): void
    {
        $query->where($this->qualifyColumn('activo'), true)
            ->whereHas('producto', fn (Builder $producto) => $producto->where('activo', true));
    }

    /**
     * Descripción corta: color y talle si tiene ("Negro / 37" o "Azul").
     */
    public function descripcion(): string
    {
        return $this->color->nombre.($this->talle !== null ? ' / '.$this->talle : '');
    }
}
