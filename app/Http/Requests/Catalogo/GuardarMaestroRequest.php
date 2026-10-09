<?php

namespace App\Http\Requests\Catalogo;

use App\Enums\Permiso;
use App\Enums\TablaMaestra;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarMaestroRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::CatalogoGestionar->value);
    }

    /**
     * El nombre es único en su tabla; la colación de la base no distingue mayúsculas.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var TablaMaestra $tabla */
        $tabla = $this->route('tabla');

        return [
            'nombre' => ['required', 'string', 'max:100', Rule::unique($tabla->value, 'nombre')->ignore($this->route('id'))],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe un registro con ese nombre.',
        ];
    }
}
