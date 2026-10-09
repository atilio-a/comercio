<?php

namespace App\Models;

use App\Enums\TipoMedioPago;
use App\Models\Concerns\TieneEstadoActivo;
use Database\Factories\MedioPagoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Medio de pago configurable (CAJ-01). No se borra: se desactiva.
 */
class MedioPago extends Model
{
    /** @use HasFactory<MedioPagoFactory> */
    use HasFactory, TieneEstadoActivo;

    protected $table = 'medios_pago';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'tipo',
        'impacta_caja',
        'dias_acreditacion',
        'requiere_referencia',
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
            'tipo' => TipoMedioPago::class,
            'impacta_caja' => 'boolean',
            'dias_acreditacion' => 'integer',
            'requiere_referencia' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PlanCuota, $this>
     */
    public function planes(): HasMany
    {
        return $this->hasMany(PlanCuota::class);
    }

    /**
     * Medios activos que se pueden usar en ingresos y egresos varios.
     *
     * @param  Builder<MedioPago>  $query
     */
    public function scopeParaMovimientoManual(Builder $query): void
    {
        $query->where('activo', true)
            ->whereNotIn('tipo', [TipoMedioPago::CuentaCorriente->value, TipoMedioPago::SaldoAFavor->value]);
    }

    /**
     * Medio con el que se registra el fondo inicial: el primer efectivo activo.
     */
    public static function efectivo(): ?self
    {
        return self::query()->activos()->where('tipo', TipoMedioPago::Efectivo->value)->orderBy('id')->first();
    }
}
