<?php

namespace App\Http\Requests\Ventas;

use App\Enums\Permiso;
use App\Services\Importes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Carrito de la venta rápida: ítems, descuentos y pagos. Lo comparten la previsualización y el
 * registro. Solo valida forma; las reglas de negocio (variantes vendibles, planes, importes,
 * pagos que suman el total) las aplica el servicio de ventas, que es la fuente de verdad.
 */
abstract class DatosVentaRequest extends FormRequest
{
    public const MAX_ITEMS = 100;

    public const MAX_PAGOS = 10;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::VentasVender->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function reglasCarrito(bool $importeObligatorio): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*' => ['array:variante_id,cantidad,descuento'],
            'items.*.variante_id' => ['required', 'integer', 'distinct'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.descuento' => ['nullable', 'string', Importes::REGLA],
            'descuento' => ['nullable', 'string', Importes::REGLA],
            'pagos' => [$importeObligatorio ? 'required' : 'nullable', 'array', 'max:'.self::MAX_PAGOS],
            'pagos.*' => ['array:medio_pago_id,plan_cuota_id,importe,referencia,lote,cupon'],
            'pagos.*.medio_pago_id' => ['required', 'integer'],
            'pagos.*.plan_cuota_id' => ['nullable', 'integer'],
            'pagos.*.importe' => [$importeObligatorio ? 'required' : 'nullable', 'string', Importes::REGLA],
            'pagos.*.referencia' => ['nullable', 'string', 'max:100'],
            'pagos.*.lote' => ['nullable', 'string', 'max:20'],
            'pagos.*.cupon' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Agregá al menos un producto.',
            'items.min' => 'Agregá al menos un producto.',
            'items.*.variante_id.distinct' => 'El producto está repetido en la venta.',
            'items.*.descuento.regex' => 'El descuento debe ser un importe con hasta dos decimales.',
            'descuento.regex' => 'El descuento debe ser un importe con hasta dos decimales.',
            'pagos.required' => 'Agregá al menos un pago.',
            'pagos.*.importe.regex' => 'El importe debe tener hasta dos decimales.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items.*.variante_id' => 'producto',
            'items.*.cantidad' => 'cantidad',
            'items.*.descuento' => 'descuento',
            'pagos.*.medio_pago_id' => 'medio de pago',
            'pagos.*.plan_cuota_id' => 'plan de cuotas',
            'pagos.*.importe' => 'importe',
            'pagos.*.referencia' => 'referencia',
        ];
    }

    /**
     * Ítems con la posición del formulario como clave.
     *
     * @return array<int, array{variante_id: int, cantidad: int, descuento: string}>
     */
    public function items(): array
    {
        return collect($this->validated('items'))
            ->map(fn (array $item) => [
                'variante_id' => (int) $item['variante_id'],
                'cantidad' => (int) $item['cantidad'],
                'descuento' => Importes::normalizar((string) ($item['descuento'] ?? '') ?: '0'),
            ])
            ->all();
    }

    /**
     * Pagos con la posición del formulario como clave.
     *
     * @return array<int, array{medio_pago_id: int, plan_cuota_id: ?int, importe: string, referencia: ?string, lote: ?string, cupon: ?string}>
     */
    public function pagos(): array
    {
        return collect($this->validated('pagos') ?? [])
            ->map(fn (array $pago) => [
                'medio_pago_id' => (int) $pago['medio_pago_id'],
                'plan_cuota_id' => isset($pago['plan_cuota_id']) ? (int) $pago['plan_cuota_id'] : null,
                'importe' => Importes::normalizar((string) ($pago['importe'] ?? '') ?: '0'),
                'referencia' => $pago['referencia'] ?? null,
                'lote' => $pago['lote'] ?? null,
                'cupon' => $pago['cupon'] ?? null,
            ])
            ->all();
    }

    public function descuentoGeneral(): string
    {
        return Importes::normalizar((string) ($this->validated('descuento') ?? '') ?: '0');
    }

    /**
     * Indica si el carrito trae algún descuento.
     */
    public function tieneDescuentos(): bool
    {
        if (Importes::aCentavos($this->descuentoGeneral()) > 0) {
            return true;
        }

        return collect($this->items())->contains(fn (array $item) => Importes::aCentavos($item['descuento']) > 0);
    }
}
