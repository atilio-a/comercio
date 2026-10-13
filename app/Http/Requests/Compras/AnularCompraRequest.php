<?php

namespace App\Http\Requests\Compras;

use App\Enums\Permiso;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Anulación de una orden de compra o de una recepción: requiere `compras.gestionar`
 * y un motivo.
 */
class AnularCompraRequest extends FormRequest
{
    /**
     * Los errores se muestran en el formulario de anulación, no en otros de la misma página.
     *
     * @var string
     */
    protected $errorBag = 'anulacion';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::ComprasGestionar->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Indicá el motivo de la anulación.',
        ];
    }

    public function motivo(): string
    {
        return trim($this->validated('motivo'));
    }
}
