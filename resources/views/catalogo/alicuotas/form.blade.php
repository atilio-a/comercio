<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $alicuota ? 'Editar alícuota' : 'Nueva alícuota' }}</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <form method="POST"
                action="{{ $alicuota ? route('catalogo.alicuotas.update', $alicuota) : route('catalogo.alicuotas.store') }}"
                class="p-4 sm:p-8 bg-white shadow-sm sm:rounded-lg space-y-6">
                @csrf
                @if ($alicuota)
                    @method('PUT')
                @endif

                <div>
                    <x-input-label for="nombre" value="Nombre" />
                    <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" maxlength="50"
                        :value="old('nombre', $alicuota?->nombre)" required autofocus />
                    <x-input-error class="mt-2" :messages="$errors->get('nombre')" />
                </div>

                <div>
                    <x-input-label for="porcentaje" value="Porcentaje" />
                    <x-text-input id="porcentaje" name="porcentaje" type="number" step="0.01" min="0" max="100" inputmode="decimal"
                        class="mt-1 block w-full" :value="old('porcentaje', $alicuota?->porcentaje)" required
                        :readonly="$enUso" aria-describedby="porcentaje-ayuda" />
                    @if ($enUso)
                        <p id="porcentaje-ayuda" class="mt-1 text-sm text-gray-600">La alícuota está asignada a productos: el porcentaje no se puede cambiar.</p>
                    @else
                        <p id="porcentaje-ayuda" class="mt-1 text-sm text-gray-600">Entre 0 y 100, con hasta dos decimales.</p>
                    @endif
                    <x-input-error class="mt-2" :messages="$errors->get('porcentaje')" />
                </div>

                <div class="flex items-center gap-3">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" id="activo" name="activo" value="1"
                        class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500"
                        @checked(old('activo', $alicuota?->activo ?? true))>
                    <label for="activo" class="text-gray-900">Activa</label>
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <a href="{{ route('catalogo.alicuotas.index') }}" class="text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
