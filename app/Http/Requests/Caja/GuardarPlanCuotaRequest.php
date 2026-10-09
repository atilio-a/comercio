<?php

namespace App\Http\Requests\Caja;

use App\Enums\Permiso;
use App\Models\MedioPago;
use App\Models\PlanCuota;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un plan de cuotas de tarjeta de crédito (CAJ-01).
 */
class GuardarPlanCuotaRequest extends FormRequest
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
            'cuotas' => [
                'required', 'integer', 'between:1,60',
                Rule::unique('planes_cuota', 'cuotas')->where('medio_pago_id', $this->medio()->id)->ignore($this->plan()),
            ],
            'recargo_pct' => ['required', 'numeric', 'decimal:0,2', 'between:0,100'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cuotas.unique' => 'El medio ya tiene un plan con esa cantidad de cuotas.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'recargo_pct' => 'recargo',
        ];
    }

    /**
     * Recargo normalizado con dos decimales, como lo guarda la base.
     */
    public function recargo(): string
    {
        return number_format((float) $this->validated('recargo_pct'), 2, '.', '');
    }

    private function medio(): MedioPago
    {
        return $this->route('medio');
    }

    private function plan(): ?PlanCuota
    {
        return $this->route('plan');
    }
}
