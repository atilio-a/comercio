<?php

namespace App\Http\Requests\Caja;

use App\Enums\Permiso;
use App\Services\Importes;
use App\Services\SucursalActiva;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Apertura de una caja de la sucursal activa con fondo inicial (CAJ-02).
 */
class AbrirCajaRequest extends FormRequest
{
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
            'caja_id' => [
                'required', 'integer',
                Rule::exists('cajas', 'id')->where('sucursal_id', app(SucursalActiva::class)->id())->where('activa', true),
            ],
            'fondo_inicial' => ['required', 'numeric', Importes::REGLA],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'caja_id.exists' => 'La caja no está disponible en esta sucursal.',
            'fondo_inicial.regex' => 'El fondo inicial debe ser un importe mayor o igual a 0, con hasta dos decimales.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'caja_id' => 'caja',
            'fondo_inicial' => 'fondo inicial',
        ];
    }

    public function fondo(): string
    {
        return Importes::normalizar((string) $this->validated('fondo_inicial'));
    }
}
