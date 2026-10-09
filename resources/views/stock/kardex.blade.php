<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Kardex · {{ $variante->producto->nombre }} · {{ $variante->descripcion() }}
            </h2>
            @if ($puedeAjustar)
                <a href="{{ route('stock.ajuste.edit', $variante->producto) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Ajustar stock
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <p class="p-4 bg-white shadow-sm sm:rounded-lg text-gray-900">
                Stock actual: <strong class="{{ $cantidad < 0 ? 'text-red-700' : '' }}">{{ $cantidad }}</strong>
                <span class="text-sm text-gray-600">· mínimo {{ $minimo }} · SKU {{ $variante->sku }}</span>
            </p>

            <form method="GET" action="{{ route('stock.kardex', $variante) }}" class="p-4 bg-white shadow-sm sm:rounded-lg grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-end">
                <div>
                    <x-input-label for="desde" value="Desde" />
                    <x-text-input id="desde" name="desde" type="date" class="mt-1 block w-full" :value="$filtros['desde'] ?? ''" />
                    <x-input-error class="mt-1" :messages="$errors->get('desde')" />
                </div>
                <div>
                    <x-input-label for="hasta" value="Hasta" />
                    <x-text-input id="hasta" name="hasta" type="date" class="mt-1 block w-full" :value="$filtros['hasta'] ?? ''" />
                    <x-input-error class="mt-1" :messages="$errors->get('hasta')" />
                </div>
                <div class="flex gap-2">
                    <x-primary-button>Filtrar</x-primary-button>
                    <a href="{{ route('stock.kardex', $variante) }}" class="inline-flex items-center px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Limpiar</a>
                </div>
            </form>

            <ol class="space-y-2" aria-label="Movimientos, del más reciente al más antiguo">
                @forelse ($movimientos as $movimiento)
                    <li class="p-4 bg-white shadow-sm sm:rounded-lg flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900">
                                {{ $movimiento->tipo->etiqueta() }}
                                <span class="{{ $movimiento->cantidad < 0 ? 'text-red-700' : 'text-green-700' }}">{{ $movimiento->cantidad > 0 ? '+' : '' }}{{ $movimiento->cantidad }}</span>
                            </p>
                            @if ($movimiento->motivo)
                                <p class="text-sm text-gray-700">{{ $movimiento->motivo }}</p>
                            @endif
                            <p class="text-xs text-gray-600">
                                Fecha {{ $movimiento->fecha->format('d/m/Y') }} · registrado {{ $movimiento->created_at->format('d/m/Y H:i') }}
                                · {{ $movimiento->user?->name ?? 'Sistema' }}
                            </p>
                        </div>
                        <p class="text-end">
                            <span class="block text-xs text-gray-600">Saldo</span>
                            <span class="block text-xl font-semibold {{ $movimiento->saldo < 0 ? 'text-red-700' : 'text-gray-900' }}">{{ $movimiento->saldo }}</span>
                        </p>
                    </li>
                @empty
                    <li class="p-4 bg-white shadow-sm sm:rounded-lg text-gray-600">Sin movimientos para el período elegido.</li>
                @endforelse
            </ol>

            {{ $movimientos->links() }}
        </div>
    </div>
</x-app-layout>
