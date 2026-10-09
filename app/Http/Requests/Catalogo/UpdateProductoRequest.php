<?php

namespace App\Http\Requests\Catalogo;

use App\Models\Producto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class UpdateProductoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->producto());
    }

    /**
     * Las opciones deben estar activas, salvo que sean las que el producto ya tiene.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $producto = $this->producto();

        return [
            'nombre' => ['required', 'string', 'max:150'],
            'categoria_id' => ['required', 'integer', $this->activoOActual('categorias', $producto->categoria_id)],
            'marca_id' => ['nullable', 'integer', $this->activoOActual('marcas', $producto->marca_id)],
            'temporada_id' => ['nullable', 'integer', $this->activoOActual('temporadas', $producto->temporada_id)],
            'alicuota_iva_id' => ['required', 'integer', $this->activoOActual('alicuotas_iva', $producto->alicuota_iva_id)],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'activo' => ['required', 'boolean'],
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function datosProducto(): array
    {
        return [
            ...$this->safe()->only(['nombre', 'categoria_id', 'marca_id', 'temporada_id', 'alicuota_iva_id', 'descripcion']),
            'activo' => $this->boolean('activo'),
        ];
    }

    private function activoOActual(string $tabla, ?int $actual): Exists
    {
        return Rule::exists($tabla, 'id')->where(function ($query) use ($actual) {
            $query->where('activo', true);

            if ($actual) {
                $query->orWhere('id', $actual);
            }
        });
    }

    private function producto(): Producto
    {
        return $this->route('producto');
    }
}
