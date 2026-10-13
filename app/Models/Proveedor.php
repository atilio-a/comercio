<?php

namespace App\Models;

use App\Models\Concerns\TieneEstadoActivo;
use App\Services\Cuit;
use Database\Factories\ProveedorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Proveedor de mercadería (COM-01). Común a todas las sucursales; no se borra, se desactiva.
 */
class Proveedor extends Model
{
    /** @use HasFactory<ProveedorFactory> */
    use HasFactory, TieneEstadoActivo;

    protected $table = 'proveedores';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'razon_social',
        'cuit',
        'contacto',
        'telefono',
        'email',
        'condiciones_pago',
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
     * CUIT con guiones para mostrar ("30-71234567-1"), o vacío.
     */
    public function cuitFormateado(): string
    {
        return Cuit::formatear($this->cuit);
    }

    /**
     * @return HasMany<OrdenCompra, $this>
     */
    public function ordenes(): HasMany
    {
        return $this->hasMany(OrdenCompra::class);
    }
}
