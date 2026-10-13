<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $cliente ? 'Editar cliente' : 'Nuevo cliente' }}</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="POST"
                action="{{ $cliente ? route('clientes.update', $cliente) : route('clientes.store') }}"
                class="p-4 sm:p-8 bg-white shadow-sm sm:rounded-lg space-y-6">
                @csrf
                @if ($cliente)
                    @method('PUT')
                @endif

                <div>
                    <x-input-label for="nombre" value="Nombre y apellido" />
                    <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" maxlength="150"
                        :value="old('nombre', $cliente?->nombre)" required autofocus autocomplete="off" />
                    <x-input-error class="mt-2" :messages="$errors->get('nombre')" />
                </div>

                <p id="identificacion-ayuda" class="text-sm text-gray-600">Indicá al menos un teléfono o un documento.</p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="telefono" value="Teléfono" />
                        <x-text-input id="telefono" name="telefono" type="tel" maxlength="50" class="mt-1 block w-full"
                            :value="old('telefono', $cliente?->telefono)" autocomplete="off" aria-describedby="identificacion-ayuda" />
                        <x-input-error class="mt-2" :messages="$errors->get('telefono')" />
                    </div>
                    <div>
                        <x-input-label for="dni_cuit" value="DNI o CUIT" />
                        <x-text-input id="dni_cuit" name="dni_cuit" type="text" inputmode="numeric" maxlength="14" class="mt-1 block w-full"
                            :value="old('dni_cuit', $cliente?->documentoFormateado())" placeholder="30.123.456" autocomplete="off" aria-describedby="identificacion-ayuda documento-ayuda" />
                        <p id="documento-ayuda" class="mt-1 text-sm text-gray-600">Con o sin puntos y guiones.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('dni_cuit')" />
                    </div>
                </div>

                <div>
                    <x-input-label for="condicion_iva" value="Condición frente al IVA" />
                    <select id="condicion_iva" name="condicion_iva" aria-describedby="condicion-ayuda"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs">
                        @foreach ($condiciones as $condicion)
                            <option value="{{ $condicion->value }}" @selected(old('condicion_iva', $cliente?->condicion_iva->value ?? App\Enums\CondicionIva::ConsumidorFinal->value) === $condicion->value)>{{ $condicion->etiqueta() }}</option>
                        @endforeach
                    </select>
                    <p id="condicion-ayuda" class="mt-1 text-sm text-gray-600">Salvo consumidor final, el cliente se identifica con CUIT.</p>
                    <x-input-error class="mt-2" :messages="$errors->get('condicion_iva')" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" name="email" type="email" maxlength="150" class="mt-1 block w-full"
                            :value="old('email', $cliente?->email)" autocomplete="off" />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>
                    @if ($usarTalles)
                        <div>
                            <x-input-label for="talle_habitual" value="Talle habitual" />
                            <x-text-input id="talle_habitual" name="talle_habitual" type="text" maxlength="10" class="mt-1 block w-full"
                                :value="old('talle_habitual', $cliente?->talle_habitual)" autocomplete="off" />
                            <x-input-error class="mt-2" :messages="$errors->get('talle_habitual')" />
                        </div>
                    @endif
                </div>

                <div>
                    <x-input-label for="observaciones" value="Observaciones" />
                    <textarea id="observaciones" name="observaciones" rows="3" maxlength="500"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs">{{ old('observaciones', $cliente?->observaciones) }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('observaciones')" />
                </div>

                <div class="flex items-center gap-3">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" id="activo" name="activo" value="1" class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500"
                        @checked(old('activo', $cliente?->activo ?? true))>
                    <label for="activo" class="text-gray-900">Activo</label>
                    <x-input-error :messages="$errors->get('activo')" />
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <a href="{{ $cliente ? route('clientes.show', $cliente) : route('clientes.index') }}" class="text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
