<?php

namespace App\Http\Requests;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends StoreUserRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->usuarioEditado());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'activo' => ['required', 'boolean'],
        ]);
    }

    /**
     * Además de las reglas del alta: nadie se desactiva a sí mismo y siempre queda
     * al menos un Administrador activo.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return array_merge(parent::after(), [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $editado = $this->usuarioEditado();
                $quedaActivo = $this->boolean('activo');

                if ($editado->is($this->user()) && ! $quedaActivo) {
                    $validator->errors()->add('activo', 'No podés desactivar tu propio usuario.');

                    return;
                }

                $esAdministrador = User::administradoresActivos()->whereKey($editado->id)->exists();
                $seguiraSiendolo = $quedaActivo && in_array(Rol::Administrador, $this->rolesPorSucursal(), true);

                if ($esAdministrador && ! $seguiraSiendolo
                    && ! User::administradoresActivos()->whereKeyNot($editado->id)->exists()) {
                    $validator->errors()->add('roles', 'Debe quedar al menos un Administrador activo.');
                }
            },
        ]);
    }

    /**
     * @return array{name: string, email: string, password?: string|null, activo: bool}
     */
    public function datosUsuario(): array
    {
        return array_merge(parent::datosUsuario(), [
            'activo' => $this->boolean('activo'),
        ]);
    }

    protected function reglaEmailUnico(): Unique
    {
        return Rule::unique(User::class)->ignore($this->usuarioEditado());
    }

    private function usuarioEditado(): User
    {
        return $this->route('user');
    }
}
