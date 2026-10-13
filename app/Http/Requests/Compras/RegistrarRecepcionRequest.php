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
 * Recepción de mercadería, contra una orden o libre (COM-02). Las filas con cantidad 0
 * se ignoran (lo no recibido de una orden). Lo pendiente y el estado de la orden se
 * verifican en el servicio, con la orden bloqueada.
 */
class RegistrarRecepcionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::ComprasRecibir->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'orden_compra_id' => ['nullable', 'integer'],
            'proveedor_id' => ['exclude_with:orden_compra_id', 'required', 'integer', Rule::exists('proveedores', 'id')->where('activo', true)],
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:'.GuardarOrdenCompraRequest::MAX_ITEMS],
            'items.*' => ['array:variante_id,cantidad,costo,actualizar_costo'],
            'items.*.variante_id' => ['required', 'integer', 'distinct', 'exists:variantes,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:0', 'max:999999'],
            'items.*.costo' => ['required', 'string', Importes::REGLA],
            'items.*.actualizar_costo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Al menos una cantidad mayor que 0; en una recepción libre, solo variantes vendibles.
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

                if ($this->items() === []) {
                    $validator->errors()->add('items', 'Ingresá la cantidad recibida de al menos un ítem.');

                    return;
                }

                if ($this->filled('orden_compra_id')) {
                    return;
                }

                $ids = array_map(fn (array $item) => $item['variante_id'], $this->items());
                $vendibles = Variante::query()->vendibles()->whereIn('variantes.id', $ids)->pluck('variantes.id')->all();

                foreach ($this->items() as $posicion => $item) {
                    if (! in_array($item['variante_id'], $vendibles, true)) {
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
            'proveedor_id.required' => 'Elegí el proveedor.',
            'proveedor_id.exists' => 'Elegí un proveedor activo.',
            'items.required' => 'Agregá al menos un ítem.',
            'items.min' => 'Agregá al menos un ítem.',
            'items.*.variante_id.distinct' => 'La variante está repetida en la recepción.',
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

    public function texto(string $campo): ?string
    {
        $texto = trim((string) $this->validated($campo));

        return $texto === '' ? null : $texto;
    }

    /**
     * Ítems con cantidad mayor que 0, con la posición del formulario como clave.
     *
     * @return array<int, array{variante_id: int, cantidad: int, costo: string, actualizar_costo: bool}>
     */
    public function items(): array
    {
        return collect($this->validated('items'))
            ->filter(fn (array $item) => (int) $item['cantidad'] > 0)
            ->map(fn (array $item) => [
                'variante_id' => (int) $item['variante_id'],
                'cantidad' => (int) $item['cantidad'],
                'costo' => Importes::normalizar((string) $item['costo']),
                'actualizar_costo' => filter_var($item['actualizar_costo'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ])
            ->all();
    }
}
