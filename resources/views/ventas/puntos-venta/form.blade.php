<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $puntoVenta ? 'Editar punto de venta '.$puntoVenta->numeroFormateado() : 'Nuevo punto de venta' }}</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <form method="POST"
                action="{{ $puntoVenta ? route('ventas.puntos-venta.update', $puntoVenta) : route('ventas.puntos-venta.store') }}"
                class="p-4 sm:p-8 bg-white shadow-sm sm:rounded-lg space-y-6">
                @csrf
                @if ($puntoVenta)
                    @method('PUT')
                @endif

                <div>
                    <x-input-label for="numero" value="Número" />
                    @if ($puntoVenta)
                        <p id="numero" class="mt-1 text-gray-900">{{ $puntoVenta->numeroFormateado() }}</p>
                        <p class="mt-1 text-sm text-gray-600">El número no se puede cambiar.</p>
                    @else
                        <x-text-input id="numero" name="numero" type="number" inputmode="numeric" min="1" max="99999" class="mt-1 block w-full"
                            :value="old('numero')" required autofocus aria-describedby="numero-ayuda" />
                        <p id="numero-ayuda" class="mt-1 text-sm text-gray-600">De 1 a 99999, único en todo el comercio.</p>
                    @endif
                    <x-input-error class="mt-2" :messages="$errors->get('numero')" />
                </div>

                <div>
                    <x-input-label for="descripcion" value="Descripción (opcional)" />
                    <x-text-input id="descripcion" name="descripcion" type="text" class="mt-1 block w-full" maxlength="100"
                        :value="old('descripcion', $puntoVenta?->descripcion)" />
                    <x-input-error class="mt-2" :messages="$errors->get('descripcion')" />
                </div>

                <div class="flex items-center gap-3">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" id="activo" name="activo" value="1"
                        class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500"
                        @checked(old('activo', $puntoVenta?->activo ?? true))>
                    <label for="activo" class="text-gray-900">Activo</label>
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <a href="{{ route('ventas.puntos-venta.index') }}" class="text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
