<?php

namespace App\Http\Requests\Caja;

use App\Enums\Permiso;
use App\Models\Caja;
use App\Services\SucursalActiva;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de una caja de la sucursal activa.
 */
class GuardarCajaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::CajaConfigurar->value);
    }

    /**
     * El nombre es único dentro de la sucursal.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => [
                'required', 'string', 'max:50',
                Rule::unique('cajas', 'nombre')->where('sucursal_id', app(SucursalActiva::class)->id())->ignore($this->caja()),
            ],
            'activa' => ['sometimes', 'boolean'],
        ];
    }

    private function caja(): ?Caja
    {
        return $this->route('caja');
    }
}
