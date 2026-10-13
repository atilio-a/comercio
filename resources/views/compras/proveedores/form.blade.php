<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $proveedor ? 'Editar proveedor' : 'Nuevo proveedor' }}</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="POST"
                action="{{ $proveedor ? route('compras.proveedores.update', $proveedor) : route('compras.proveedores.store') }}"
                class="p-4 sm:p-8 bg-white shadow-sm sm:rounded-lg space-y-6">
                @csrf
                @if ($proveedor)
                    @method('PUT')
                @endif

                <div>
                    <x-input-label for="razon_social" value="Razón social" />
                    <x-text-input id="razon_social" name="razon_social" type="text" class="mt-1 block w-full" maxlength="150"
                        :value="old('razon_social', $proveedor?->razon_social)" required autofocus />
                    <x-input-error class="mt-2" :messages="$errors->get('razon_social')" />
                </div>

                <div>
                    <x-input-label for="cuit" value="CUIT (opcional)" />
                    <x-text-input id="cuit" name="cuit" type="text" inputmode="numeric" maxlength="13" class="mt-1 block w-full"
                        :value="old('cuit', $proveedor?->cuitFormateado())" placeholder="30-71234567-1" aria-describedby="cuit-ayuda" />
                    <p id="cuit-ayuda" class="mt-1 text-sm text-gray-600">Con o sin guiones. Se verifica el dígito verificador.</p>
                    <x-input-error class="mt-2" :messages="$errors->get('cuit')" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="contacto" value="Contacto" />
                        <x-text-input id="contacto" name="contacto" type="text" maxlength="100" class="mt-1 block w-full"
                            :value="old('contacto', $proveedor?->contacto)" autocomplete="off" />
                        <x-input-error class="mt-2" :messages="$errors->get('contacto')" />
                    </div>
                    <div>
                        <x-input-label for="telefono" value="Teléfono" />
                        <x-text-input id="telefono" name="telefono" type="tel" maxlength="50" class="mt-1 block w-full"
                            :value="old('telefono', $proveedor?->telefono)" autocomplete="off" />
                        <x-input-error class="mt-2" :messages="$errors->get('telefono')" />
                    </div>
                </div>

                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" name="email" type="email" maxlength="150" class="mt-1 block w-full"
                        :value="old('email', $proveedor?->email)" autocomplete="off" />
                    <x-input-error class="mt-2" :messages="$errors->get('email')" />
                </div>

                <div>
                    <x-input-label for="condiciones_pago" value="Condiciones de pago" />
                    <x-text-input id="condiciones_pago" name="condiciones_pago" type="text" maxlength="255" class="mt-1 block w-full"
                        :value="old('condiciones_pago', $proveedor?->condiciones_pago)" placeholder="30 días" />
                    <x-input-error class="mt-2" :messages="$errors->get('condiciones_pago')" />
                </div>

                <div class="flex items-center gap-3">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" id="activo" name="activo" value="1" class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500"
                        @checked(old('activo', $proveedor?->activo ?? true))>
                    <label for="activo" class="text-gray-900">Activo</label>
                    <x-input-error :messages="$errors->get('activo')" />
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <a href="{{ route('compras.proveedores.index') }}" class="text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
