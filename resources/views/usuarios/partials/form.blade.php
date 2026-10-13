@php($rolesActuales ??= [])

<div class="space-y-6">
    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $usuario?->name)" required autocomplete="off" />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="email" :value="__('Email')" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $usuario?->email)" required autocomplete="off" />
        <x-input-error class="mt-2" :messages="$errors->get('email')" />
    </div>

    <div>
        <x-input-label for="password" :value="$usuario ? 'Contraseña nueva (dejar vacío para no cambiarla)' : 'Contraseña inicial'" />
        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" :required="! $usuario" autocomplete="new-password" />
        <x-input-error class="mt-2" :messages="$errors->get('password')" />
    </div>

    <div>
        <x-input-label for="password_confirmation" value="Repetir contraseña" />
        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" :required="! $usuario" autocomplete="new-password" />
    </div>

    <div class="flex items-start gap-3">
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" id="activo" name="activo" value="1" aria-describedby="activo-ayuda"
            class="mt-1 rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500"
            @checked(old('activo', $usuario?->activo ?? true))>
        <div>
            <label for="activo" class="font-medium text-gray-900">Activo</label>
            <p id="activo-ayuda" class="text-sm text-gray-600">Un usuario inactivo no puede ingresar. Sus datos e historial se conservan.</p>
            <x-input-error class="mt-1" :messages="$errors->get('activo')" />
        </div>
    </div>

    <div class="flex items-start gap-3">
        <input type="hidden" name="vendedor" value="0">
        <input type="checkbox" id="vendedor" name="vendedor" value="1" aria-describedby="vendedor-ayuda"
            class="mt-1 rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500"
            @checked(old('vendedor', $usuario?->esVendedor() ?? false))>
        <div>
            <label for="vendedor" class="font-medium text-gray-900">Es vendedor</label>
            <p id="vendedor-ayuda" class="text-sm text-gray-600">Se le pueden asignar ventas en las sucursales donde opera.</p>
            <x-input-error class="mt-1" :messages="$errors->get('vendedor')" />
        </div>
    </div>

    <fieldset>
        <legend class="font-medium text-gray-900">Sucursales y rol</legend>
        <p class="text-sm text-gray-600">Elegí el rol del usuario en cada sucursal donde opera.</p>

        <div class="mt-3 space-y-3">
            @foreach ($sucursales as $sucursal)
                <div>
                    <x-input-label :for="'rol-'.$sucursal->id" :value="$sucursal->nombre" />
                    <select id="rol-{{ $sucursal->id }}" name="roles[{{ $sucursal->id }}]"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs">
                        <option value="">Sin asignar</option>
                        @foreach ($opcionesRol as $rol)
                            <option value="{{ $rol->value }}" @selected(old('roles.'.$sucursal->id, $rolesActuales[$sucursal->id] ?? null) === $rol->value)>{{ $rol->value }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('roles.'.$sucursal->id)" />
                </div>
            @endforeach
        </div>

        <x-input-error class="mt-2" :messages="$errors->get('roles')" />
    </fieldset>
</div>
