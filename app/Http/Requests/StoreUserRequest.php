<?php

namespace App\Http\Requests;

use App\Enums\Rol;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', $this->reglaEmailUnico()],
            'password' => ['required', 'confirmed', Password::defaults()],
            'activo' => ['sometimes', 'boolean'],
            'roles' => ['required', 'array:'.implode(',', $this->idsSucursalesActivas())],
            'roles.*' => ['nullable', Rule::enum(Rol::class)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('roles') || $validator->errors()->has('roles.*')) {
                    return;
                }

                if (array_filter($this->rolesPorSucursal()) === []) {
                    $validator->errors()->add('roles', 'Asigná al menos una sucursal con su rol.');
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
            'roles.array' => 'Se enviaron sucursales que no existen o no están activas.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'roles.*' => 'rol',
        ];
    }

    /**
     * Rol elegido por id de sucursal (null = sin asignar).
     *
     * @return array<int, Rol|null>
     */
    public function rolesPorSucursal(): array
    {
        $roles = [];

        foreach ((array) $this->input('roles', []) as $sucursalId => $rol) {
            $roles[(int) $sucursalId] = is_string($rol) ? Rol::tryFrom($rol) : null;
        }

        return $roles;
    }

    /**
     * @return array{name: string, email: string, password?: string|null, activo: bool}
     */
    public function datosUsuario(): array
    {
        return [
            'name' => $this->validated('name'),
            'email' => $this->validated('email'),
            'password' => $this->validated('password'),
            'activo' => $this->boolean('activo', true),
        ];
    }

    protected function reglaEmailUnico(): Unique
    {
        return Rule::unique(User::class);
    }

    /**
     * @return list<int>
     */
    protected function idsSucursalesActivas(): array
    {
        return Sucursal::activas()->pluck('id')->all();
    }
}
