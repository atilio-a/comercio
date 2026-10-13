<?php

namespace App\Http\Requests\Compras;

use App\Enums\Permiso;
use App\Models\Proveedor;
use App\Rules\CuitValido;
use App\Services\Cuit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un proveedor (COM-01).
 */
class GuardarProveedorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::ComprasGestionar->value);
    }

    /**
     * El CUIT se compara sin guiones, así el único detecta "30-71234567-1" y "30712345671".
     */
    protected function prepareForValidation(): void
    {
        $cuit = $this->input('cuit');

        $this->merge([
            'cuit' => is_string($cuit) && trim($cuit) !== '' ? Cuit::normalizar($cuit) : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'razon_social' => ['required', 'string', 'max:150'],
            'cuit' => ['nullable', 'string', new CuitValido, Rule::unique('proveedores', 'cuit')->ignore($this->proveedor())],
            'contacto' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'condiciones_pago' => ['nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cuit.unique' => 'Ya hay un proveedor con ese CUIT.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'razon_social' => 'razón social',
            'cuit' => 'CUIT',
            'telefono' => 'teléfono',
            'condiciones_pago' => 'condiciones de pago',
        ];
    }

    /**
     * @return array{razon_social: string, cuit: ?string, contacto: ?string, telefono: ?string, email: ?string, condiciones_pago: ?string, activo: bool}
     */
    public function datos(bool $activoPorDefecto): array
    {
        $texto = fn (string $campo): ?string => ($valor = trim((string) $this->validated($campo))) === '' ? null : $valor;

        return [
            'razon_social' => trim($this->validated('razon_social')),
            'cuit' => $this->validated('cuit'),
            'contacto' => $texto('contacto'),
            'telefono' => $texto('telefono'),
            'email' => $texto('email'),
            'condiciones_pago' => $texto('condiciones_pago'),
            'activo' => $this->boolean('activo', $activoPorDefecto),
        ];
    }

    private function proveedor(): ?Proveedor
    {
        return $this->route('proveedor');
    }
}
