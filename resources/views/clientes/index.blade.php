<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Clientes</h2>

            @if ($puedeGestionar)
                <a href="{{ route('clientes.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Nuevo cliente
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-3">
            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            <form method="GET" action="{{ route('clientes.index') }}" role="search" class="p-4 bg-white shadow-sm sm:rounded-lg flex flex-wrap items-end gap-3">
                <div class="grow">
                    <x-input-label for="q" value="Buscar por nombre, documento o teléfono" />
                    <x-text-input id="q" name="q" type="search" class="mt-1 block w-full" :value="$filtros['q'] ?? ''" autocomplete="off" />
                </div>
                <x-primary-button>Buscar</x-primary-button>
            </form>

            <ul class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100" aria-label="Clientes">
                @forelse ($clientes as $cliente)
                    <li class="flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <a href="{{ route('clientes.show', $cliente) }}" class="font-medium underline-offset-2 hover:underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500 {{ $cliente->activo ? 'text-gray-900' : 'text-gray-500 line-through' }}">
                                {{ $cliente->nombre }}
                                @unless ($cliente->activo)
                                    <span class="sr-only">(inactivo)</span>
                                @endunless
                            </a>
                            <p class="text-sm text-gray-600">
                                {{ $cliente->dni_cuit ? $cliente->documentoFormateado() : 'Sin documento' }}
                                @if ($cliente->telefono)
                                    · <a href="tel:{{ preg_replace('/[^\d+]/', '', $cliente->telefono) }}" class="underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">{{ $cliente->telefono }}</a>
                                @endif
                                · {{ $cliente->condicion_iva->etiqueta() }}
                                @if ($usarTalles && $cliente->talle_habitual)
                                    · Talle {{ $cliente->talle_habitual }}
                                @endif
                            </p>
                        </div>
                        @if ($puedeGestionar)
                            <a href="{{ route('clientes.edit', $cliente) }}" class="shrink-0 text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                Editar<span class="sr-only"> {{ $cliente->nombre }}</span>
                            </a>
                        @endif
                    </li>
                @empty
                    <li class="p-4 text-gray-600">No hay clientes.</li>
                @endforelse
            </ul>

            {{ $clientes->links() }}
        </div>
    </div>
</x-app-layout>
