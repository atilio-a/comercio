<?php

namespace App\Http\Requests;

use App\Enums\Parametro;
use App\Enums\Permiso;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateParametrosRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::ParametrosEditar->value);
    }

    /**
     * Cada parámetro conocido es obligatorio y sí/no; no se aceptan claves desconocidas.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $claves = array_column(Parametro::cases(), 'value');

        $rules = [
            'parametros' => ['required', 'array:'.implode(',', $claves)],
        ];

        foreach (Parametro::cases() as $parametro) {
            $rules['parametros.'.$parametro->value] = $parametro->sePuedeHabilitar()
                ? ['required', 'boolean']
                : ['required', 'boolean', 'declined'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            'parametros.array' => 'Se enviaron parámetros que no existen.',
        ];

        foreach (Parametro::cases() as $parametro) {
            if (! $parametro->sePuedeHabilitar()) {
                $messages['parametros.'.$parametro->value.'.declined'] = ':attribute todavía no está disponible.';
            }
        }

        return $messages;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (Parametro::cases() as $parametro) {
            $attributes['parametros.'.$parametro->value] = $parametro->etiqueta();
        }

        return $attributes;
    }

    /**
     * Valores validados por parámetro.
     *
     * @return array<string, bool>
     */
    public function valores(): array
    {
        return array_map(
            fn (mixed $valor): bool => filter_var($valor, FILTER_VALIDATE_BOOLEAN),
            $this->validated('parametros'),
        );
    }
}
