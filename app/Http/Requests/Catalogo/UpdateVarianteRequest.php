<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\Catalogo\Concerns\ValidaGrillaVariantes;
use App\Models\Variante;
use App\Services\GeneradorCodigos;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edición de una variante. Color y talle no se aceptan: identifican la variante.
 */
class UpdateVarianteRequest extends FormRequest
{
    use ValidaGrillaVariantes;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('producto'));
    }

    /**
     * Los códigos son únicos en todo el catálogo, incluidas las variantes inactivas.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $variante = $this->variante();

        return [
            'sku' => ['required', 'string', 'regex:'.GeneradorCodigos::PATRON_CODIGO, Rule::unique('variantes', 'sku')->ignore($variante)],
            'codigo_barras' => ['required', 'string', 'regex:'.GeneradorCodigos::PATRON_CODIGO, Rule::unique('variantes', 'codigo_barras')->ignore($variante)],
            ...$this->reglasImportes(),
            'activo' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.unique' => 'El SKU ya está en uso.',
            'codigo_barras.unique' => 'El código de barras ya está en uso.',
            'sku.regex' => 'El SKU debe tener entre 4 y 50 caracteres: letras, números, guion, guion bajo o punto.',
            'codigo_barras.regex' => 'El código de barras debe tener entre 4 y 50 caracteres: letras, números, guion, guion bajo o punto.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sku' => 'SKU',
            'codigo_barras' => 'código de barras',
            ...$this->atributosGrilla(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function datosVariante(): array
    {
        return [
            ...$this->safe()->only(['sku', 'codigo_barras', 'costo', 'precio_final']),
            'activo' => $this->boolean('activo'),
        ];
    }

    private function variante(): Variante
    {
        return $this->route('variante');
    }
}
