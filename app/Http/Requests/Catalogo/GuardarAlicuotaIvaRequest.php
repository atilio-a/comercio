<?php

namespace App\Http\Requests\Catalogo;

use App\Enums\Permiso;
use App\Models\AlicuotaIva;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GuardarAlicuotaIvaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::CatalogoGestionar->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:50', Rule::unique('alicuotas_iva', 'nombre')->ignore($this->alicuota())],
            'porcentaje' => ['required', 'numeric', 'decimal:0,2', 'between:0,100'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * El porcentaje de una alícuota asignada a productos no se puede cambiar.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $alicuota = $this->alicuota();

                if (! $alicuota || $validator->errors()->has('porcentaje')) {
                    return;
                }

                if ($this->porcentaje() !== $alicuota->porcentaje && $alicuota->productos()->exists()) {
                    $validator->errors()->add('porcentaje', 'La alícuota está en uso por productos: no se puede cambiar el porcentaje.');
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
            'porcentaje' => 'porcentaje',
        ];
    }

    /**
     * Porcentaje normalizado con dos decimales, como lo guarda la base.
     */
    public function porcentaje(): string
    {
        return number_format((float) $this->input('porcentaje'), 2, '.', '');
    }

    private function alicuota(): ?AlicuotaIva
    {
        return $this->route('alicuota');
    }
}
