<?php

namespace App\Models;

use App\Services\CuentasCorrientes;
use App\Services\Importes;
use Database\Factories\CuentaCorrienteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuenta corriente de un cliente (una por cliente). Solo la modifica {@see CuentasCorrientes}.
 *
 * Convención del saldo: `saldo = Σ debe − Σ haber`. **Positivo = el cliente debe;
 * negativo = el cliente tiene saldo a favor.** Es un valor cacheado: la fuente de verdad son
 * los movimientos (comando `cuentas-corrientes:recalcular`).
 *
 * Sin `admite_credito` la cuenta solo guarda saldo a favor y permite usarlo; no admite deuda.
 */
class CuentaCorriente extends Model
{
    /** @use HasFactory<CuentaCorrienteFactory> */
    use HasFactory;

    protected $table = 'cuentas_corrientes';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cliente_id',
        'admite_credito',
        'limite_credito',
        'bloqueada',
        'saldo',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'admite_credito' => false,
        'limite_credito' => '0.00',
        'bloqueada' => false,
        'saldo' => '0.00',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'admite_credito' => 'boolean',
            'limite_credito' => 'decimal:2',
            'bloqueada' => 'boolean',
            'saldo' => 'decimal:2',
        ];
    }

    /**
     * Saldo a favor del cliente, positivo ("0.00" si no tiene).
     */
    public function saldoAFavor(): string
    {
        return Importes::aDecimal(max(0, -Importes::aCentavos((string) $this->saldo)));
    }

    /**
     * Lo que el cliente debe, positivo ("0.00" si no debe).
     */
    public function deuda(): string
    {
        return Importes::aDecimal(max(0, Importes::aCentavos((string) $this->saldo)));
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return HasMany<CcMovimiento, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(CcMovimiento::class, 'cuenta_id');
    }
}
