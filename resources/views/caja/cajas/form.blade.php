<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $caja ? 'Editar caja' : 'Nueva caja' }}</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <form method="POST"
                action="{{ $caja ? route('caja.cajas.update', $caja) : route('caja.cajas.store') }}"
                class="p-4 sm:p-8 bg-white shadow-sm sm:rounded-lg space-y-6">
                @csrf
                @if ($caja)
                    @method('PUT')
                @endif

                <div>
                    <x-input-label for="nombre" value="Nombre" />
                    <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" maxlength="50"
                        :value="old('nombre', $caja?->nombre)" required autofocus />
                    <x-input-error class="mt-2" :messages="$errors->get('nombre')" />
                </div>

                <div class="flex items-center gap-3">
                    <input type="hidden" name="activa" value="0">
                    <input type="checkbox" id="activa" name="activa" value="1"
                        class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500"
                        @checked(old('activa', $caja?->activa ?? true))>
                    <label for="activa" class="text-gray-900">Activa</label>
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <a href="{{ route('caja.cajas.index') }}" class="text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
