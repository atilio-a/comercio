<?php

namespace App\Http\Requests\Ventas;

use App\Enums\Permiso;
use App\Models\PuntoVenta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un punto de venta de la sucursal activa (VEN-07). El número es único en
 * todo el sistema y solo se indica en el alta: después no cambia.
 */
class GuardarPuntoVentaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::PuntosVentaConfigurar->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'numero' => $this->puntoVenta()
                ? ['prohibited']
                : ['required', 'integer', 'between:1,99999', Rule::unique('puntos_venta', 'numero')],
            'descripcion' => ['nullable', 'string', 'max:100'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero.unique' => 'Ya hay un punto de venta con ese número.',
            'numero.prohibited' => 'El número de un punto de venta no se puede cambiar.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'numero' => 'número',
            'descripcion' => 'descripción',
        ];
    }

    public function descripcion(): ?string
    {
        $texto = trim((string) $this->validated('descripcion'));

        return $texto === '' ? null : $texto;
    }

    private function puntoVenta(): ?PuntoVenta
    {
        return $this->route('puntoVenta');
    }
}
