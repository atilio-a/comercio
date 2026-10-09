<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xs sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="font-medium">Hola, {{ Auth::user()->name }}.</p>
                    <p class="mt-1 text-sm text-gray-600">El tablero con ventas, stock y alertas se agrega con los próximos módulos.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
