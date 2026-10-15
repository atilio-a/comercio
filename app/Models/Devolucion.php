<?php

namespace App\Models;

use App\Enums\ResolucionDevolucion;
use App\Enums\TipoComprobante;
use App\Services\Devoluciones;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * Devolución de ítems de una venta confirmada (VEN-03). Se registra solo con
 * {@see Devoluciones}: reingresa el stock, emite la nota de crédito y acredita el importe en la
 * cuenta corriente del cliente o lo reembolsa desde la caja. Inmutable.
 */
class Devolucion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'devoluciones';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sucursal_id',
        'venta_id',
        'cliente_id',
        'fecha',
        'motivo',
        'resolucion',
        'importe',
        'neto',
        'iva',
        'medio_pago_id',
        'caja_sesion_id',
        'user_id',
        'dispositivo',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'resolucion' => ResolucionDevolucion::class,
            'importe' => 'decimal:2',
            'neto' => 'decimal:2',
            'iva' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Una devolución no se puede modificar.'));
        static::deleting(fn () => throw new LogicException('Las devoluciones no se pueden borrar.'));
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * @return BelongsTo<Venta, $this>
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * Cliente al que se acreditó el saldo; null en un reembolso a consumidor final.
     *
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Medio del reembolso, si lo hubo.
     *
     * @return BelongsTo<MedioPago, $this>
     */
    public function medioPago(): BelongsTo
    {
        return $this->belongsTo(MedioPago::class);
    }

    /**
     * Sesión de caja del reembolso, si lo hubo.
     *
     * @return BelongsTo<CajaSesion, $this>
     */
    public function cajaSesion(): BelongsTo
    {
        return $this->belongsTo(CajaSesion::class);
    }

    /**
     * Usuario que registró la devolución.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<DevolucionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DevolucionItem::class);
    }

    /**
     * Nota de crédito emitida con la devolución.
     *
     * @return HasOne<Comprobante, $this>
     */
    public function notaCredito(): HasOne
    {
        return $this->hasOne(Comprobante::class)->where('tipo', TipoComprobante::NotaCreditoInterna->value);
    }
}
