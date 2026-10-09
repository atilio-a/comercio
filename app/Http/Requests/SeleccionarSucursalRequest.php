<?php

namespace App\Http\Requests;

use App\Enums\Parametro;
use App\Services\Parametros;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SeleccionarSucursalRequest extends FormRequest
{
    /**
     * Solo se elige sucursal con multi-sucursal habilitado y entre las asignadas y activas.
     */
    public function authorize(Parametros $parametros): bool
    {
        if (! $parametros->habilitado(Parametro::MultiSucursal)) {
            return false;
        }

        return $this->user()
            ->sucursales()
            ->activas()
            ->whereKey($this->integer('sucursal_id'))
            ->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sucursal_id' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sucursal_id' => 'sucursal',
        ];
    }
}
