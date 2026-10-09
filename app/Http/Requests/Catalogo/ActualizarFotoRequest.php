<?php

namespace App\Http\Requests\Catalogo;

use App\Models\Producto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cambio del color asociado a una imagen.
 */
class ActualizarFotoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->producto());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'color_id' => ['nullable', 'integer', Rule::in($this->producto()->variantes()->distinct()->pluck('color_id')->all())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'color_id.in' => 'El color elegido no corresponde a ninguna variante del producto.',
        ];
    }

    private function producto(): Producto
    {
        return $this->route('producto');
    }
}
