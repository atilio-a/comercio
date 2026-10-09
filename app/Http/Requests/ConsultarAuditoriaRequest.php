<?php

namespace App\Http\Requests;

use App\Enums\Permiso;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarAuditoriaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::AuditoriaVer->value);
    }

    /**
     * Filtros opcionales: período, usuario y acción.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'user_id' => ['nullable', 'integer'],
            'accion' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'desde' => 'desde',
            'hasta' => 'hasta',
            'user_id' => 'usuario',
            'accion' => 'acción',
        ];
    }
}
