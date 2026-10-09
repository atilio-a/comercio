<?php

namespace App\Models;

use Database\Factories\PlanCuotaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plan de cuotas de una tarjeta de crédito, con recargo porcentual (CAJ-01).
 */
class PlanCuota extends Model
{
    /** @use HasFactory<PlanCuotaFactory> */
    use HasFactory;

    protected $table = 'planes_cuota';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'medio_pago_id',
        'cuotas',
        'recargo_pct',
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
            'cuotas' => 'integer',
            'recargo_pct' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MedioPago, $this>
     */
    public function medioPago(): BelongsTo
    {
        return $this->belongsTo(MedioPago::class);
    }
}
