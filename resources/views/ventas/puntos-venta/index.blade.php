<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Puntos de venta de la sucursal</h2>

            <a href="{{ route('ventas.puntos-venta.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Nuevo punto de venta
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-3">
            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            <p class="px-4 sm:px-0 text-sm text-gray-600">Cada punto de venta numera sus comprobantes por tipo. El número es único en todo el comercio y no se puede cambiar.</p>

            <ul class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100" aria-label="Puntos de venta">
                @forelse ($puntosVenta as $puntoVenta)
                    <li class="flex items-center justify-between gap-3 p-4">
                        <p class="min-w-0 {{ $puntoVenta->activo ? 'text-gray-900' : 'text-gray-500 line-through' }}">
                            {{ $puntoVenta->nombre() }}
                            @unless ($puntoVenta->activo)
                                <span class="sr-only">(inactivo)</span>
                            @endunless
                        </p>
                        <a href="{{ route('ventas.puntos-venta.edit', $puntoVenta) }}" class="shrink-0 text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                            Editar<span class="sr-only"> el punto de venta {{ $puntoVenta->numeroFormateado() }}</span>
                        </a>
                    </li>
                @empty
                    <li class="p-4 text-sm text-gray-600">La sucursal no tiene puntos de venta: creá uno para poder vender.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-app-layout>
