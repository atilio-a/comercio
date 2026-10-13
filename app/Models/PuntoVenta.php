<?php

namespace App\Models;

use App\Models\Concerns\TieneEstadoActivo;
use Database\Factories\PuntoVentaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Punto de venta de una sucursal (VEN-07). El número es único en todo el sistema y no cambia
 * después del alta; no se borra, se desactiva. Numera sus comprobantes por tipo.
 */
class PuntoVenta extends Model
{
    /** @use HasFactory<PuntoVentaFactory> */
    use HasFactory, TieneEstadoActivo;

    protected $table = 'puntos_venta';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sucursal_id',
        'numero',
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
            'numero' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /**
     * Número con cinco dígitos, como en los comprobantes: 1 → "00001".
     */
    public function numeroFormateado(): string
    {
        return sprintf('%05d', $this->numero);
    }

    /**
     * Número y descripción para listas: "00001 – Mostrador".
     */
    public function nombre(): string
    {
        return $this->numeroFormateado().($this->descripcion ? ' – '.$this->descripcion : '');
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
}
