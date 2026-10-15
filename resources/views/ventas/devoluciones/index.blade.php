<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Devoluciones</h2>
            <a href="{{ route('ventas.index') }}" class="text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                Ver ventas
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-3">
            <form method="GET" action="{{ route('ventas.devoluciones.index') }}" class="p-4 bg-white shadow-sm sm:rounded-lg grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-end">
                <div>
                    <x-input-label for="desde" value="Desde" />
                    <x-text-input id="desde" name="desde" type="date" class="mt-1 block w-full" :value="$filtros['desde'] ?? ''" />
                </div>
                <div>
                    <x-input-label for="hasta" value="Hasta" />
                    <x-text-input id="hasta" name="hasta" type="date" class="mt-1 block w-full" :value="$filtros['hasta'] ?? ''" />
                    <x-input-error class="mt-1" :messages="$errors->get('hasta')" />
                </div>
                <div>
                    <x-primary-button>Filtrar</x-primary-button>
                </div>
            </form>

            <ul class="space-y-2" aria-label="Devoluciones, de la más reciente a la más antigua">
                @forelse ($devoluciones as $devolucion)
                    <li class="p-4 bg-white shadow-sm sm:rounded-lg flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900">
                                <a href="{{ route('ventas.devoluciones.show', $devolucion) }}" class="underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                    Devolución N° {{ $devolucion->id }}
                                </a>
                                <span class="text-sm font-normal text-gray-600">
                                    · Venta N° <a href="{{ route('ventas.show', $devolucion->venta_id) }}" class="underline">{{ $devolucion->venta_id }}</a>
                                </span>
                                @if ($devolucion->notaCredito)
                                    <span class="text-sm font-normal text-gray-600">· NC {{ $devolucion->notaCredito->numeroCompleto() }}</span>
                                @endif
                            </p>
                            <p class="text-sm text-gray-600">
                                {{ $devolucion->fecha->format('d/m/Y') }}
                                · {{ $devolucion->cliente?->nombre ?? 'Consumidor final' }}
                                · {{ $devolucion->resolucion->etiqueta() }}
                                · Registró {{ $devolucion->user?->name ?? 'Sistema' }}
                            </p>
                            <p class="text-xs text-gray-500">Motivo: {{ $devolucion->motivo }}</p>
                        </div>
                        <p class="font-semibold text-gray-900">$ {{ App\Services\Formato::importe($devolucion->importe) }}</p>
                    </li>
                @empty
                    <li class="p-4 bg-white shadow-sm sm:rounded-lg text-gray-600">No hay devoluciones para los filtros elegidos.</li>
                @endforelse
            </ul>

            {{ $devoluciones->links() }}
        </div>
    </div>
</x-app-layout>
