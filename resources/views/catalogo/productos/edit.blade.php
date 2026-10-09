<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar producto</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('catalogo.productos.update', $producto) }}" class="p-4 sm:p-8 bg-white shadow-sm sm:rounded-lg space-y-6">
                @csrf
                @method('PUT')

                @include('catalogo.productos.partials.campos-producto')

                <div class="flex items-start gap-3">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" id="activo" name="activo" value="1" aria-describedby="activo-ayuda"
                        class="mt-1 rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500"
                        @checked(old('activo', $producto->activo))>
                    <div>
                        <label for="activo" class="font-medium text-gray-900">Activo</label>
                        <p id="activo-ayuda" class="text-sm text-gray-600">Un producto inactivo no se puede vender, con ninguna de sus variantes. No se borra.</p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <a href="{{ route('catalogo.productos.show', $producto) }}" class="text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
