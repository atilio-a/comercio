<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Proveedores</h2>

            @if ($puedeGestionar)
                <a href="{{ route('compras.proveedores.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Nuevo proveedor
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-3">
            @include('compras.partials.menu')

            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            <form method="GET" action="{{ route('compras.proveedores.index') }}" role="search" class="p-4 bg-white shadow-sm sm:rounded-lg flex flex-wrap items-end gap-3">
                <div class="grow">
                    <x-input-label for="q" value="Buscar por razón social o CUIT" />
                    <x-text-input id="q" name="q" type="search" class="mt-1 block w-full" :value="$filtros['q'] ?? ''" autocomplete="off" />
                </div>
                <x-primary-button>Buscar</x-primary-button>
            </form>

            <ul class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100" aria-label="Proveedores">
                @forelse ($proveedores as $proveedor)
                    <li class="flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="{{ $proveedor->activo ? 'text-gray-900' : 'text-gray-500 line-through' }}">
                                {{ $proveedor->razon_social }}
                                @unless ($proveedor->activo)
                                    <span class="sr-only">(inactivo)</span>
                                @endunless
                            </p>
                            <p class="text-sm text-gray-600">
                                @if ($proveedor->cuit)
                                    CUIT {{ $proveedor->cuitFormateado() }}
                                @else
                                    Sin CUIT
                                @endif
                                @if ($proveedor->contacto)
                                    · {{ $proveedor->contacto }}
                                @endif
                                @if ($proveedor->telefono)
                                    · <a href="tel:{{ preg_replace('/[^\d+]/', '', $proveedor->telefono) }}" class="underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">{{ $proveedor->telefono }}</a>
                                @endif
                                @if ($proveedor->condiciones_pago)
                                    · {{ $proveedor->condiciones_pago }}
                                @endif
                            </p>
                        </div>
                        @if ($puedeGestionar)
                            <a href="{{ route('compras.proveedores.edit', $proveedor) }}" class="shrink-0 text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                Editar<span class="sr-only"> {{ $proveedor->razon_social }}</span>
                            </a>
                        @endif
                    </li>
                @empty
                    <li class="p-4 text-gray-600">No hay proveedores.</li>
                @endforelse
            </ul>

            {{ $proveedores->links() }}
        </div>
    </div>
</x-app-layout>
