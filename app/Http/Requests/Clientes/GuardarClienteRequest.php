<?php

namespace App\Http\Requests\Clientes;

use App\Enums\CondicionIva;
use App\Enums\Parametro;
use App\Enums\Permiso;
use App\Models\Cliente;
use App\Rules\DocumentoClienteValido;
use App\Services\DocumentoCliente;
use App\Services\Parametros;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta (completa o rápida desde la venta) y edición de un cliente (CLI-01).
 */
class GuardarClienteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::ClientesGestionar->value);
    }

    /**
     * El documento se compara solo con dígitos, así el único detecta "30.123.456" y "30123456".
     */
    protected function prepareForValidation(): void
    {
        $documento = $this->input('dni_cuit');

        $this->merge([
            'dni_cuit' => is_string($documento) && trim($documento) !== '' ? DocumentoCliente::normalizar($documento) : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reglas = [
            'nombre' => ['required', 'string', 'max:150'],
            'dni_cuit' => ['nullable', 'string', new DocumentoClienteValido, Rule::unique('clientes', 'dni_cuit')->ignore($this->cliente())],
            'telefono' => ['nullable', 'required_without:dni_cuit', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'condicion_iva' => ['sometimes', Rule::enum(CondicionIva::class)],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'activo' => ['sometimes', 'boolean'],
        ];

        if ($this->usaTalles()) {
            $reglas['talle_habitual'] = ['nullable', 'string', 'max:10'];
        }

        return $reglas;
    }

    /**
     * Las condiciones distintas de consumidor final se identifican con CUIT.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('dni_cuit') || $validator->errors()->has('condicion_iva')) {
                    return;
                }

                $condicion = $this->condicion();
                $documento = (string) $this->input('dni_cuit');

                if ($condicion->exigeCuit() && ! DocumentoCliente::esCuit($documento)) {
                    $validator->errors()->add('dni_cuit', "Para un cliente {$condicion->etiqueta()} indicá su CUIT.");
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
            'dni_cuit.unique' => 'Ya hay un cliente con ese documento.',
            'telefono.required_without' => 'Indicá un teléfono o un documento.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'dni_cuit' => 'documento',
            'telefono' => 'teléfono',
            'talle_habitual' => 'talle habitual',
            'condicion_iva' => 'condición frente al IVA',
        ];
    }

    /**
     * Datos a guardar. Con el parámetro de talles deshabilitado no incluye `talle_habitual`,
     * así el valor guardado se conserva.
     *
     * @return array<string, mixed>
     */
    public function datos(bool $activoPorDefecto): array
    {
        $texto = fn (string $campo): ?string => ($valor = trim((string) $this->validated($campo))) === '' ? null : $valor;

        $datos = [
            'nombre' => trim($this->validated('nombre')),
            'dni_cuit' => $this->validated('dni_cuit'),
            'telefono' => $texto('telefono'),
            'email' => $texto('email'),
            'condicion_iva' => $this->condicion(),
            'observaciones' => $texto('observaciones'),
            'activo' => $this->boolean('activo', $activoPorDefecto),
        ];

        if ($this->usaTalles()) {
            $datos['talle_habitual'] = $texto('talle_habitual');
        }

        return $datos;
    }

    private function condicion(): CondicionIva
    {
        $valor = $this->input('condicion_iva');

        return (is_string($valor) ? CondicionIva::tryFrom($valor) : null)
            ?? $this->cliente()?->condicion_iva
            ?? CondicionIva::ConsumidorFinal;
    }

    private function usaTalles(): bool
    {
        return app(Parametros::class)->habilitado(Parametro::UsarTalles);
    }

    private function cliente(): ?Cliente
    {
        return $this->route('cliente');
    }
}
