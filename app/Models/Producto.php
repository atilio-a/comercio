<?php

namespace App\Models;

use App\Models\Concerns\TieneEstadoActivo;
use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use HasFactory, TieneEstadoActivo;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'categoria_id',
        'marca_id',
        'temporada_id',
        'alicuota_iva_id',
        'descripcion',
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
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * @return BelongsTo<Marca, $this>
     */
    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    /**
     * @return BelongsTo<Temporada, $this>
     */
    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    /**
     * @return BelongsTo<AlicuotaIva, $this>
     */
    public function alicuotaIva(): BelongsTo
    {
        return $this->belongsTo(AlicuotaIva::class);
    }

    /**
     * @return HasMany<Variante, $this>
     */
    public function variantes(): HasMany
    {
        return $this->hasMany(Variante::class);
    }

    /**
     * Imágenes en orden de carga.
     *
     * @return HasMany<ProductoFoto, $this>
     */
    public function fotos(): HasMany
    {
        return $this->hasMany(ProductoFoto::class)->orderBy('orden');
    }

    /**
     * Imagen principal, para cargar con `with()` en listados sin N+1.
     *
     * @return HasOne<ProductoFoto, $this>
     */
    public function fotoPrincipal(): HasOne
    {
        return $this->hasOne(ProductoFoto::class)->where('es_principal', true);
    }

    /**
     * Imagen para un color: la primera de ese color o, si no hay, la principal.
     */
    public function fotoPara(?Color $color): ?ProductoFoto
    {
        $fotos = $this->relationLoaded('fotos') ? $this->fotos : $this->fotos()->get();

        return ($color ? $fotos->firstWhere('color_id', $color->id) : null)
            ?? $fotos->firstWhere('es_principal', true);
    }
}
