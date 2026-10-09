<?php

namespace App\Http\Requests\Caja;

use App\Enums\Permiso;
use App\Enums\TipoMedioPago;
use App\Enums\TipoMovimientoCaja;
use App\Models\MedioPago;
use App\Services\Importes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Ingreso o egreso vario en la caja abierta del usuario, siempre con motivo (CAJ-03).
 */
class RegistrarMovimientoCajaRequest extends FormRequest
{
    private ?MedioPago $medio = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::CajaOperar->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in([TipoMovimientoCaja::Ingreso->value, TipoMovimientoCaja::Egreso->value])],
            'medio_pago_id' => [
                'required', 'integer',
                Rule::exists('medios_pago', 'id')
                    ->where('activo', true)
                    ->whereNotIn('tipo', [TipoMedioPago::CuentaCorriente->value, TipoMedioPago::SaldoAFavor->value]),
            ],
            'importe' => ['required', 'numeric', Importes::REGLA, 'gt:0'],
            'motivo' => ['required', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * La referencia es obligatoria cuando el medio la requiere.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('medio_pago_id')) {
                    return;
                }

                if ($this->medio()->requiere_referencia && trim((string) $this->input('referencia')) === '') {
                    $validator->errors()->add('referencia', "Indicá la referencia del pago con {$this->medio()->nombre}.");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Indicá el motivo del movimiento.',
            'importe.regex' => 'El importe debe tener hasta dos decimales.',
            'medio_pago_id.exists' => 'Elegí un medio de pago habilitado para la caja.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'medio_pago_id' => 'medio de pago',
        ];
    }

    public function tipo(): TipoMovimientoCaja
    {
        return TipoMovimientoCaja::from($this->validated('tipo'));
    }

    public function medio(): MedioPago
    {
        return $this->medio ??= MedioPago::findOrFail($this->input('medio_pago_id'));
    }

    /**
     * Importe con el signo del tipo: el egreso sale de la caja.
     */
    public function importeConSigno(): string
    {
        $centavos = Importes::aCentavos(Importes::normalizar((string) $this->validated('importe')));

        return Importes::aDecimal($this->tipo()->signo() * $centavos);
    }
}
