<?php

namespace App\Http\Requests\Catalogo;

use App\Models\Producto;
use App\Rules\ImagenPermitida;
use App\Services\GaleriaProducto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubirFotosRequest extends FormRequest
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
            'fotos' => ['required', 'array', 'min:1', 'max:'.GaleriaProducto::MAXIMO_POR_PRODUCTO],
            'fotos.*' => ['bail', 'required', 'file', 'max:5120', new ImagenPermitida, 'dimensions:max_width=4000,max_height=4000'],
            'miniaturas' => ['nullable', 'array'],
            'miniaturas.*' => ['bail', 'nullable', 'file', 'max:512', new ImagenPermitida, 'dimensions:max_width=4000,max_height=4000'],
            'color_id' => ['nullable', 'integer', Rule::in($this->coloresDelProducto())],
        ];
    }

    /**
     * Cupo de imágenes del producto.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('fotos')) {
                    return;
                }

                $disponibles = GaleriaProducto::MAXIMO_POR_PRODUCTO - $this->producto()->fotos()->count();

                if (count((array) $this->file('fotos')) > $disponibles) {
                    $validator->errors()->add('fotos', 'Un producto puede tener hasta '.GaleriaProducto::MAXIMO_POR_PRODUCTO." imágenes; quedan {$disponibles} lugares.");
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
            'fotos.required' => 'Elegí al menos una imagen.',
            'fotos.max' => 'Podés subir hasta '.GaleriaProducto::MAXIMO_POR_PRODUCTO.' imágenes a la vez.',
            'fotos.*.max' => 'Cada imagen puede pesar hasta 5 MB.',
            'fotos.*.dimensions' => 'Cada imagen puede medir hasta 4000 px por lado.',
            'color_id.in' => 'El color elegido no corresponde a ninguna variante del producto.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fotos.*' => 'imagen',
            'miniaturas.*' => 'miniatura',
            'color_id' => 'color',
        ];
    }

    /**
     * @return list<int>
     */
    protected function coloresDelProducto(): array
    {
        return $this->producto()->variantes()->distinct()->pluck('color_id')->all();
    }

    protected function producto(): Producto
    {
        return $this->route('producto');
    }
}
