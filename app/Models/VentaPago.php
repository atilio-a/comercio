<?php

namespace App\Models;

use App\Services\Importes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Pago de una venta (pagos combinados, VEN-02). `importe` es la parte de la venta que cubre;
 * con tarjeta en cuotas el medio cobra además el `recargo` del plan (foto en `cuotas` y
 * `recargo_pct`). Inmutable.
 */
class VentaPago extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'venta_pagos';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'venta_id',
        'medio_pago_id',
        'plan_cuota_id',
        'cuotas',
        'recargo_pct',
        'importe',
        'recargo',
        'referencia',
        'lote',
        'cupon',
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
            'importe' => 'decimal:2',
            'recargo' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los pagos de una venta no se pueden modificar.'));
        static::deleting(fn () => throw new LogicException('Los pagos de una venta no se pueden borrar.'));
    }

    /**
     * Lo que cobra el medio: importe más recargo.
     */
    public function cobrado(): string
    {
        return Importes::aDecimal(Importes::aCentavos((string) $this->importe) + Importes::aCentavos((string) $this->recargo));
    }

    /**
     * @return BelongsTo<Venta, $this>
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * @return BelongsTo<MedioPago, $this>
     */
    public function medioPago(): BelongsTo
    {
        return $this->belongsTo(MedioPago::class);
    }

    /**
     * @return BelongsTo<PlanCuota, $this>
     */
    public function planCuota(): BelongsTo
    {
        return $this->belongsTo(PlanCuota::class);
    }
}
