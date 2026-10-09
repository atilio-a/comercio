<?php

namespace App\Http\Requests\Caja;

use App\Enums\Permiso;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Contraasiento de un movimiento de caja (CAJ-05). Solo quien supervisa la caja.
 */
class ContraasientoRequest extends FormRequest
{
    /**
     * Bolsa de errores propia: el detalle de la sesión tiene un formulario por movimiento.
     *
     * @var string
     */
    protected $errorBag = 'contraasiento';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::CajaSupervisar->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Indicá el motivo del contraasiento.',
        ];
    }
}
