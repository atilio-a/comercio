<?php

namespace App\Http\Requests\Compras;

use App\Enums\Permiso;
use App\Models\Variante;
use App\Services\Importes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de una orden de compra en borrador (COM-01).
 */
class GuardarOrdenCompraRequest extends FormRequest
{
    public const MAX_ITEMS = 200;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::ComprasGestionar->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'proveedor_id' => ['required', 'integer', Rule::exists('proveedores', 'id')->where('activo', true)],
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*' => ['array:variante_id,cantidad,costo'],
            'items.*.variante_id' => ['required', 'integer', 'distinct', 'exists:variantes,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:999999'],
            'items.*.costo' => ['required', 'string', Importes::REGLA],
        ];
    }

    /**
     * Solo se piden variantes vendibles (variante y producto activos).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ids = collect($this->validated('items'))->pluck('variante_id')->map(fn ($id) => (int) $id);
                $vendibles = Variante::query()->vendibles()->whereIn('variantes.id', $ids)->pluck('variantes.id')->all();

                foreach ($ids as $posicion => $id) {
                    if (! in_array($id, $vendibles, true)) {
                        $validator->errors()->add("items.{$posicion}.variante_id", 'La variante o su producto están inactivos.');
                    }
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
            'proveedor_id.exists' => 'Elegí un proveedor activo.',
            'items.required' => 'Agregá al menos un ítem.',
            'items.min' => 'Agregá al menos un ítem.',
            'items.*.variante_id.distinct' => 'La variante está repetida en la orden.',
            'items.*.costo.regex' => 'El costo debe ser un importe con hasta dos decimales.',
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'proveedor_id' => 'proveedor',
            'items.*.variante_id' => 'variante',
            'items.*.cantidad' => 'cantidad',
            'items.*.costo' => 'costo',
        ];
    }

    public function observaciones(): ?string
    {
        $texto = trim((string) $this->validated('observaciones'));

        return $texto === '' ? null : $texto;
    }

    /**
     * @return list<array{variante_id: int, cantidad: int, costo: string}>
     */
    public function items(): array
    {
        return collect($this->validated('items'))
            ->map(fn (array $item) => [
                'variante_id' => (int) $item['variante_id'],
                'cantidad' => (int) $item['cantidad'],
                'costo' => Importes::normalizar((string) $item['costo']),
            ])
            ->values()
            ->all();
    }
}
