<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo producto</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('catalogo.productos.store') }}" class="p-4 sm:p-8 bg-white shadow-sm sm:rounded-lg space-y-6">
                @csrf

                @include('catalogo.productos.partials.campos-producto', ['producto' => null])

                @include('catalogo.productos.partials.grilla')

                <div class="flex items-center gap-4">
                    <x-primary-button>Crear producto</x-primary-button>
                    <a href="{{ route('catalogo.productos.index') }}" class="text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
