<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\Catalogo\Concerns\ValidaGrillaVariantes;
use App\Models\Producto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductoRequest extends FormRequest
{
    use ValidaGrillaVariantes;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Producto::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')->where('activo', true)],
            'marca_id' => ['nullable', 'integer', Rule::exists('marcas', 'id')->where('activo', true)],
            'temporada_id' => ['nullable', 'integer', Rule::exists('temporadas', 'id')->where('activo', true)],
            'alicuota_iva_id' => ['required', 'integer', Rule::exists('alicuotas_iva', 'id')->where('activo', true)],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            ...$this->reglasGrilla(),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validarGrilla($validator)];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'categoria_id.required' => 'La categoría es obligatoria.',
            ...$this->mensajesGrilla(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'categoria_id' => 'categoría',
            'marca_id' => 'marca',
            'temporada_id' => 'temporada',
            'alicuota_iva_id' => 'alícuota de IVA',
            'descripcion' => 'descripción',
            ...$this->atributosGrilla(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function datosProducto(): array
    {
        return [
            ...$this->safe()->only(['nombre', 'categoria_id', 'marca_id', 'temporada_id', 'alicuota_iva_id', 'descripcion']),
            'activo' => true,
        ];
    }
}
