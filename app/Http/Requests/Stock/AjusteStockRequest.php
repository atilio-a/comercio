<?php

namespace App\Http\Requests\Stock;

use App\Enums\Permiso;
use App\Models\Producto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Ajuste por conteo de las variantes de un producto, con mínimos.
 * Un contado vacío significa "sin cambio".
 */
class AjusteStockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::StockAjustar->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ids = implode(',', $this->producto()->variantes()->pluck('id')->all());

        return [
            'contado' => ['nullable', 'array:'.$ids],
            'contado.*' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'minimo' => ['nullable', 'array:'.$ids],
            'minimo.*' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * El motivo es obligatorio cuando se carga algún conteo.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->conteos() !== [] && trim((string) $this->input('motivo')) === '') {
                    $validator->errors()->add('motivo', 'Indicá el motivo del ajuste.');
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
            'contado.*.min' => 'La cantidad contada no puede ser negativa.',
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
            'contado.array' => 'Se enviaron variantes que no pertenecen al producto.',
            'minimo.array' => 'Se enviaron variantes que no pertenecen al producto.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'contado.*' => 'cantidad contada',
            'minimo.*' => 'mínimo',
        ];
    }

    /**
     * Cantidades contadas por id de variante, solo las ingresadas.
     *
     * @return array<int, int>
     */
    public function conteos(): array
    {
        return $this->enteros('contado');
    }

    /**
     * Mínimos por id de variante, solo los ingresados.
     *
     * @return array<int, int>
     */
    public function minimos(): array
    {
        return $this->enteros('minimo');
    }

    /**
     * @return array<int, int>
     */
    private function enteros(string $campo): array
    {
        $valores = [];

        foreach ((array) $this->input($campo, []) as $varianteId => $valor) {
            if ($valor !== null && $valor !== '') {
                $valores[(int) $varianteId] = (int) $valor;
            }
        }

        return $valores;
    }

    private function producto(): Producto
    {
        return $this->route('producto');
    }
}
