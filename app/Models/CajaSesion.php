<?php

namespace App\Models;

use App\Enums\EstadoSesionCaja;
use App\Services\Importes;
use App\Services\SesionesCaja;
use Database\Factories\CajaSesionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Sesión (turno) de una caja: desde la apertura con fondo inicial hasta el cierre con
 * arqueo (CAJ-02). Se abre y se cierra con {@see SesionesCaja}. No se borra, y una vez
 * cerrada no se modifica.
 */
class CajaSesion extends Model
{
    /** @use HasFactory<CajaSesionFactory> */
    use HasFactory;

    protected $table = 'caja_sesiones';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'caja_id',
        'sucursal_id',
        'user_id',
        'fecha',
        'apertura',
        'fondo_inicial',
        'cierre',
        'estado',
        'justificacion',
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
            'apertura' => 'datetime',
            'cierre' => 'datetime',
            'fondo_inicial' => 'decimal:2',
            'estado' => EstadoSesionCaja::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (CajaSesion $sesion) {
            if ($sesion->getOriginal('estado') === EstadoSesionCaja::Cerrada) {
                throw new LogicException('Una sesión de caja cerrada no se puede modificar.');
            }
        });

        static::deleting(fn () => throw new LogicException('Las sesiones de caja no se pueden borrar.'));
    }

    public function estaAbierta(): bool
    {
        return $this->estado === EstadoSesionCaja::Abierta;
    }

    /**
     * Saldo esperado por medio de pago: la suma de los importes de la sesión.
     *
     * @return array<int, string> Importe decimal por id de medio de pago.
     */
    public function esperadoPorMedio(): array
    {
        return $this->movimientos()
            ->toBase()
            ->selectRaw('medio_pago_id, SUM(importe) as total')
            ->groupBy('medio_pago_id')
            ->pluck('total', 'medio_pago_id')
            ->map(fn ($total) => Importes::normalizar((string) $total))
            ->all();
    }

    /**
     * @return BelongsTo<Caja, $this>
     */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Usuario que abrió la caja y la cierra.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CajaMovimiento, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(CajaMovimiento::class);
    }

    /**
     * @return HasMany<CajaSesionArqueo, $this>
     */
    public function arqueos(): HasMany
    {
        return $this->hasMany(CajaSesionArqueo::class);
    }
}
