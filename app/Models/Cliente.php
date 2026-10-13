<?php

namespace App\Models;

use App\Enums\CondicionIva;
use App\Models\Concerns\TieneEstadoActivo;
use App\Services\CuentasCorrientes;
use App\Services\DocumentoCliente;
use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Cliente del comercio (CLI-01). Común a todas las sucursales; no se borra, se desactiva.
 * El talle habitual se muestra solo con el parámetro "Usar talles en productos" habilitado.
 */
class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory, TieneEstadoActivo;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'dni_cuit',
        'telefono',
        'email',
        'talle_habitual',
        'condicion_iva',
        'observaciones',
        'activo',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'condicion_iva' => 'consumidor_final',
        'activo' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condicion_iva' => CondicionIva::class,
            'activo' => 'boolean',
        ];
    }

    /**
     * Documento para mostrar: DNI con puntos o CUIT con guiones, o vacío.
     */
    public function documentoFormateado(): string
    {
        return DocumentoCliente::formatear($this->dni_cuit);
    }

    /**
     * Busca por parte del nombre o del teléfono y, si el texto tiene al menos 3 dígitos,
     * por parte del documento (así un DNI encuentra también el CUIT que lo contiene).
     *
     * @param  Builder<Cliente>  $query
     */
    public function scopeBuscar(Builder $query, string $texto): void
    {
        $texto = trim($texto);
        $digitos = DocumentoCliente::normalizar($texto);

        $query->where(function (Builder $query) use ($texto, $digitos) {
            $query->where('nombre', 'like', '%'.$texto.'%')
                ->orWhere('telefono', 'like', '%'.$texto.'%');

            if (preg_match('/^\d{3,}$/', $digitos) === 1) {
                $query->orWhere('dni_cuit', 'like', '%'.$digitos.'%');
            }
        });
    }

    /**
     * Cuenta corriente, si ya tiene. Se crea al registrar el primer movimiento con
     * {@see CuentasCorrientes}.
     *
     * @return HasOne<CuentaCorriente, $this>
     */
    public function cuentaCorriente(): HasOne
    {
        return $this->hasOne(CuentaCorriente::class);
    }
}
