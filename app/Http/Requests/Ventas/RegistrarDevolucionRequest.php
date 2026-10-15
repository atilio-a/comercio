<?php

namespace App\Http\Requests\Ventas;

use App\Enums\Permiso;
use App\Enums\ResolucionDevolucion;
use App\Models\Cliente;
use App\Models\MedioPago;
use App\Models\Venta;
use App\Services\SucursalActiva;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Devolución de ítems de una venta (VEN-03): requiere poder devolver la venta y, para el
 * reembolso en dinero, `ventas.reembolsar`. Las cantidades se envían por id de ítem
 * (`cantidades[id] = n`); el tope de lo devolvible lo verifica el servicio con la venta
 * bloqueada. Las ventas de otra sucursal responden 404.
 */
class RegistrarDevolucionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(SucursalActiva $sucursalActiva): bool
    {
        $venta = $this->venta();
        abort_unless((int) $venta->sucursal_id === (int) $sucursalActiva->id(), 404);

        return $this->user()->can('devolver', $venta);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cantidades' => ['required', 'array'],
            'cantidades.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'motivo' => ['required', 'string', 'max:200'],
            'resolucion' => ['required', Rule::enum(ResolucionDevolucion::class)],
            'cliente_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')->where('activo', true)],
            'medio_pago_id' => ['nullable', 'required_if:resolucion,'.ResolucionDevolucion::Reembolso->value, 'integer', Rule::exists('medios_pago', 'id')],
            'referencia' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Al menos una cantidad mayor que 0, y el reembolso solo con su permiso.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['cantidades', 'cantidades.*'])) {
                    return;
                }

                if ($this->cantidades() === []) {
                    $validator->errors()->add('cantidades', 'Elegí al menos un producto para devolver.');
                }
            },
            function (Validator $validator) {
                if ($this->input('resolucion') === ResolucionDevolucion::Reembolso->value && ! $this->user()->can(Permiso::VentasReembolsar->value)) {
                    $validator->errors()->add('resolucion', 'No tenés permiso para reembolsar dinero: devolvé con saldo a favor.');
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
            'motivo.required' => 'Indicá el motivo de la devolución.',
            'resolucion.required' => 'Elegí cómo se resuelve la devolución.',
            'medio_pago_id.required_if' => 'Elegí el medio del reembolso.',
            'cliente_id.exists' => 'Elegí un cliente activo.',
        ];
    }

    public function venta(): Venta
    {
        return $this->route('venta');
    }

    /**
     * Unidades a devolver por id de ítem, solo las mayores que 0.
     *
     * @return array<int, int>
     */
    public function cantidades(): array
    {
        $cantidades = [];

        foreach ((array) $this->input('cantidades', []) as $itemId => $cantidad) {
            if ((int) $cantidad > 0) {
                $cantidades[(int) $itemId] = (int) $cantidad;
            }
        }

        return $cantidades;
    }

    public function motivo(): string
    {
        return trim($this->validated('motivo'));
    }

    public function resolucion(): ResolucionDevolucion
    {
        return ResolucionDevolucion::from($this->validated('resolucion'));
    }

    public function cliente(): ?Cliente
    {
        $id = $this->validated('cliente_id');

        return $id ? Cliente::query()->find($id) : null;
    }

    public function medioReembolso(): ?MedioPago
    {
        $id = $this->validated('medio_pago_id');

        return $this->resolucion() === ResolucionDevolucion::Reembolso && $id ? MedioPago::query()->find($id) : null;
    }

    public function referencia(): ?string
    {
        $referencia = trim((string) $this->validated('referencia'));

        return $referencia === '' ? null : $referencia;
    }
}
