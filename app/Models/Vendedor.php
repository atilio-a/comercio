<?php

namespace App\Models;

use App\Models\Concerns\TieneEstadoActivo;
use App\Services\Vendedores;
use Database\Factories\VendedorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Usuario que vende (VND-01). Uno por usuario; no se borra, se desactiva, porque las ventas
 * lo referencian. Los disponibles por sucursal salen de {@see Vendedores}.
 */
class Vendedor extends Model
{
    /** @use HasFactory<VendedorFactory> */
    use HasFactory, TieneEstadoActivo;

    protected $table = 'vendedores';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
