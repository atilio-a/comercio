<?php

namespace App\Models;

use App\Enums\EstadoVenta;
use App\Enums\Permiso;
use App\Enums\TipoComprobante;
use App\Services\Ventas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * Venta del mostrador (VEN-01, VEN-02). Se registra y se anula solo con {@see Ventas}.
 *
 * No se borra ni se edita: el único cambio admitido es la anulación de una venta confirmada
 * (estado, quién, cuándo y motivo). Los importes son `decimal(14,2)`; `descuento` suma los
 * descuentos de los ítems y el general, y `total = subtotal_neto + iva`.
 */
class Venta extends Model
{
    /**
     * Columnas que la anulación puede cambiar.
     */
    private const COLUMNAS_ANULACION = ['estado', 'anulada_por', 'anulada_at', 'motivo_anulacion', 'updated_at'];

    protected $table = 'ventas';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sucursal_id',
        'fecha',
        'cliente_id',
        'vendedor_id',
        'caja_sesion_id',
        'punto_venta_id',
        'estado',
        'subtotal_neto',
        'iva',
        'descuento',
        'recargo',
        'total',
        'descuento_autorizado_por',
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
            'estado' => EstadoVenta::class,
            'subtotal_neto' => 'decimal:2',
            'iva' => 'decimal:2',
            'descuento' => 'decimal:2',
            'recargo' => 'decimal:2',
            'total' => 'decimal:2',
            'anulada_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Venta $venta) {
            $soloAnulacion = array_diff(array_keys($venta->getDirty()), self::COLUMNAS_ANULACION) === [];
            $anula = $venta->getOriginal('estado') === EstadoVenta::Confirmada && $venta->estado === EstadoVenta::Anulada;

            if (! $soloAnulacion || ! $anula) {
                throw new LogicException('Una venta no se puede modificar: solo se puede anular.');
            }
        });

        static::deleting(fn () => throw new LogicException('Las ventas no se pueden borrar.'));
    }

    public function estaAnulada(): bool
    {
        return $this->estado === EstadoVenta::Anulada;
    }

    /**
     * Ventas que el usuario puede ver: todas con `ventas.ver_todas`; si no, solo aquellas en
     * las que es el vendedor o que registró (doc 02, rol Vendedor). No filtra por sucursal.
     *
     * @param  Builder<Venta>  $query
     */
    public function scopeVisiblesPara(Builder $query, User $user): void
    {
        if ($user->can(Permiso::VentasVerTodas->value)) {
            return;
        }

        $query->where(function (Builder $query) use ($user) {
            $query->where($this->qualifyColumn('user_id'), $user->id)
                ->orWhereIn($this->qualifyColumn('vendedor_id'), Vendedor::query()->select('id')->where('user_id', $user->id));
        });
    }

    /**
     * Indica si el usuario es el vendedor de la venta o quien la registró.
     */
    public function esPropiaDe(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id
            || Vendedor::query()->whereKey($this->vendedor_id)->where('user_id', $user->id)->exists();
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Cliente; null es consumidor final.
     *
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return BelongsTo<Vendedor, $this>
     */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    /**
     * Sesión de caja de los cobros, si alguno pasó por caja.
     *
     * @return BelongsTo<CajaSesion, $this>
     */
    public function cajaSesion(): BelongsTo
    {
        return $this->belongsTo(CajaSesion::class);
    }

    /**
     * @return BelongsTo<PuntoVenta, $this>
     */
    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class);
    }

    /**
     * Usuario que registró la venta.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Quien autorizó el descuento general.
     *
     * @return BelongsTo<User, $this>
     */
    public function autorizanteDescuento(): BelongsTo
    {
        return $this->belongsTo(User::class, 'descuento_autorizado_por');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulada_por');
    }

    /**
     * @return HasMany<VentaItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(VentaItem::class);
    }

    /**
     * @return HasMany<VentaPago, $this>
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(VentaPago::class);
    }

    /**
     * @return HasMany<Comprobante, $this>
     */
    public function comprobantes(): HasMany
    {
        return $this->hasMany(Comprobante::class);
    }

    /**
     * Comprobante emitido con la venta.
     *
     * @return HasOne<Comprobante, $this>
     */
    public function comprobante(): HasOne
    {
        return $this->hasOne(Comprobante::class)->where('tipo', TipoComprobante::VentaInterna->value);
    }

    /**
     * Devoluciones de ítems de la venta.
     *
     * @return HasMany<Devolucion, $this>
     */
    public function devoluciones(): HasMany
    {
        return $this->hasMany(Devolucion::class);
    }

    /**
     * Comprobante emitido al anularla, si fue anulada.
     *
     * @return HasOne<Comprobante, $this>
     */
    public function comprobanteAnulacion(): HasOne
    {
        return $this->hasOne(Comprobante::class)->where('tipo', TipoComprobante::AnulacionInterna->value);
    }
}
