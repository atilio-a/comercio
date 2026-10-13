<?php

namespace App\Http\Requests\Ventas;

use App\Enums\Permiso;
use App\Models\Cliente;
use App\Models\PuntoVenta;
use App\Models\User;
use App\Models\Vendedor;
use App\Services\SucursalActiva;
use App\Services\Vendedores;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registro de una venta (VEN-01, VEN-02). Además del carrito resuelve:
 * - el vendedor, que debe estar disponible en la sucursal activa;
 * - el cliente, activo y opcional (sin cliente es consumidor final);
 * - el punto de venta: el indicado o el primero activo de la sucursal;
 * - quién autoriza los descuentos: el propio usuario si tiene `ventas.autorizar_descuentos`;
 *   si no, el usuario cuyo email y contraseña se indican (activo, de la sucursal y con el
 *   permiso), con 5 intentos fallidos por minuto como máximo.
 */
class RegistrarVentaRequest extends DatosVentaRequest
{
    public const INTENTOS_AUTORIZACION = 5;

    private ?PuntoVenta $puntoVentaResuelto = null;

    private ?User $autorizadorResuelto = null;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->reglasCarrito(importeObligatorio: true),
            'vendedor_id' => ['required', 'integer'],
            'cliente_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')->where('activo', true)],
            'punto_venta_id' => ['nullable', 'integer'],
            'autorizacion' => ['nullable', 'array:email,password'],
            'autorizacion.email' => ['nullable', 'string', 'max:255'],
            'autorizacion.password' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'vendedor_id.required' => 'Elegí el vendedor.',
            'cliente_id.exists' => 'Elegí un cliente activo.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! $this->vendedor()) {
                    $validator->errors()->add('vendedor_id', 'Elegí un vendedor disponible en esta sucursal.');
                }

                $this->puntoVentaResuelto = $this->resolverPuntoVenta($validator);

                if ($this->tieneDescuentos()) {
                    $this->autorizadorResuelto = $this->resolverAutorizador($validator);
                }
            },
        ];
    }

    public function vendedor(): ?Vendedor
    {
        return app(Vendedores::class)->disponibles()->firstWhere('id', (int) $this->validated('vendedor_id'));
    }

    public function cliente(): ?Cliente
    {
        $id = $this->validated('cliente_id');

        return $id ? Cliente::query()->findOrFail($id) : null;
    }

    public function puntoVenta(): PuntoVenta
    {
        return $this->puntoVentaResuelto;
    }

    public function autorizador(): ?User
    {
        return $this->autorizadorResuelto;
    }

    private function resolverPuntoVenta(Validator $validator): ?PuntoVenta
    {
        $puntoVenta = PuntoVenta::query()
            ->activos()
            ->where('sucursal_id', app(SucursalActiva::class)->id())
            ->when($this->validated('punto_venta_id'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('numero')
            ->first();

        if (! $puntoVenta) {
            $validator->errors()->add('punto_venta_id', $this->filled('punto_venta_id')
                ? 'Elegí un punto de venta activo de esta sucursal.'
                : 'La sucursal no tiene un punto de venta activo: pedile al Administrador que cree uno.');
        }

        return $puntoVenta;
    }

    private function resolverAutorizador(Validator $validator): ?User
    {
        if ($this->user()->can(Permiso::VentasAutorizarDescuentos->value)) {
            return $this->user();
        }

        $email = trim((string) $this->input('autorizacion.email'));
        $password = (string) $this->input('autorizacion.password');

        if ($email === '' || $password === '') {
            $validator->errors()->add('autorizacion', 'Los descuentos requieren la autorización de un usuario con permiso: indicá su email y contraseña.');

            return null;
        }

        $clave = 'autorizar-descuento:'.$this->user()->id.'|'.$this->ip();

        if (RateLimiter::tooManyAttempts($clave, self::INTENTOS_AUTORIZACION)) {
            $validator->errors()->add('autorizacion.email', 'Demasiados intentos de autorización. Probá de nuevo en '.RateLimiter::availableIn($clave).' segundos.');

            return null;
        }

        $autorizador = User::query()->where('email', $email)->first();
        $valido = $autorizador
            && $autorizador->activo
            && Hash::check($password, $autorizador->password)
            && $autorizador->sucursales()->whereKey(app(SucursalActiva::class)->id())->exists()
            && Gate::forUser($autorizador)->allows(Permiso::VentasAutorizarDescuentos->value);

        if (! $valido) {
            RateLimiter::hit($clave, 60);
            $validator->errors()->add('autorizacion.email', 'Email o contraseña incorrectos, o el usuario no puede autorizar descuentos en esta sucursal.');

            return null;
        }

        RateLimiter::clear($clave);

        return $autorizador;
    }
}
