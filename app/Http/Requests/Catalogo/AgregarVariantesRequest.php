<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\Catalogo\Concerns\ValidaGrillaVariantes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AgregarVariantesRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->reglasGrilla();
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
        return $this->mensajesGrilla();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->atributosGrilla();
    }
}
