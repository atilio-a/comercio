<?php

namespace App\Models;

use App\Services\GaleriaProducto;
use Database\Factories\ProductoFotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Imagen de un producto, opcionalmente asociada a un color. Los archivos viven en el
 * disco `local` (privado) y se administran con {@see GaleriaProducto}.
 */
class ProductoFoto extends Model
{
    /** @use HasFactory<ProductoFotoFactory> */
    use HasFactory;

    protected $table = 'producto_fotos';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'producto_id',
        'color_id',
        'ruta',
        'ruta_miniatura',
        'es_principal',
        'orden',
        'ancho',
        'alto',
        'bytes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
            'orden' => 'integer',
            'ancho' => 'integer',
            'alto' => 'integer',
            'bytes' => 'integer',
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
     * Texto alternativo: producto y, si tiene, color.
     */
    public function textoAlternativo(): string
    {
        return $this->producto->nombre.($this->color ? ' – '.$this->color->nombre : '');
    }
}
