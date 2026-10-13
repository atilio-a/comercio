<?php

namespace App\Models;

use App\Enums\TipoComprobante;
use App\Services\Comprobantes\EmisorComprobante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Documento emitido por un {@see EmisorComprobante} (VEN-04). Único por tipo, punto de venta y
 * número. Inmutable: su impresión sale solo de `datos_json`, la foto tomada al emitirlo.
 */
class Comprobante extends Model
{
    public const UPDATED_AT = null;

    /**
     * Leyenda obligatoria de los comprobantes internos.
     */
    public const LEYENDA_INTERNO = 'Documento no válido como factura';

    protected $table = 'comprobantes';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sucursal_id',
        'venta_id',
        'devolucion_id',
        'tipo',
        'punto_venta_id',
        'numero',
        'fecha',
        'cae',
        'cae_vto',
        'emisor',
        'comprobante_anulado_id',
        'datos_json',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoComprobante::class,
            'numero' => 'integer',
            'fecha' => 'date',
            'cae_vto' => 'date',
            'datos_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Un comprobante emitido no se puede modificar.'));
        static::deleting(fn () => throw new LogicException('Un comprobante emitido no se puede borrar.'));
    }

    /**
     * Punto de venta y número con el formato de los comprobantes: "00001-00000042".
     */
    public static function formatearNumero(int $puntoVenta, int $numero): string
    {
        return sprintf('%05d-%08d', $puntoVenta, $numero);
    }

    /**
     * Número completo tal como se emitió.
     */
    public function numeroCompleto(): string
    {
        return (string) $this->datos_json['numero_completo'];
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
     * @return BelongsTo<PuntoVenta, $this>
     */
    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class);
    }

    /**
     * Comprobante que este anula, si es un comprobante de anulación.
     *
     * @return BelongsTo<Comprobante, $this>
     */
    public function comprobanteAnulado(): BelongsTo
    {
        return $this->belongsTo(self::class, 'comprobante_anulado_id');
    }
}
