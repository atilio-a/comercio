<?php

namespace App\Http\Requests\Caja;

use App\Enums\Permiso;
use App\Enums\TipoMedioPago;
use App\Models\MedioPago;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de un medio de pago (CAJ-01).
 */
class GuardarMedioPagoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::CajaConfigurar->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:50', Rule::unique('medios_pago', 'nombre')->ignore($this->medio())],
            'tipo' => ['required', Rule::enum(TipoMedioPago::class)],
            'impacta_caja' => ['sometimes', 'boolean'],
            'dias_acreditacion' => ['required', 'integer', 'between:0,365'],
            'requiere_referencia' => ['sometimes', 'boolean'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Un medio con planes de cuotas no puede dejar de ser tarjeta de crédito.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $medio = $this->medio();

                if (! $medio || $validator->errors()->has('tipo')) {
                    return;
                }

                if (! TipoMedioPago::from($this->input('tipo'))->admitePlanes() && $medio->planes()->exists()) {
                    $validator->errors()->add('tipo', 'El medio tiene planes de cuotas: solo puede ser tarjeta de crédito.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'dias_acreditacion' => 'días de acreditación',
        ];
    }

    /**
     * @return array{nombre: string, tipo: string, impacta_caja: bool, dias_acreditacion: int, requiere_referencia: bool, activo: bool}
     */
    public function datos(bool $activoPorDefecto): array
    {
        return [
            'nombre' => trim($this->validated('nombre')),
            'tipo' => $this->validated('tipo'),
            'impacta_caja' => $this->boolean('impacta_caja'),
            'dias_acreditacion' => (int) $this->validated('dias_acreditacion'),
            'requiere_referencia' => $this->boolean('requiere_referencia'),
            'activo' => $this->boolean('activo', $activoPorDefecto),
        ];
    }

    private function medio(): ?MedioPago
    {
        return $this->route('medio');
    }
}
