<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $verTodas ? 'Ventas' : 'Mis ventas' }}</h2>

            @if ($puedeVender)
                <a href="{{ route('ventas.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Nueva venta
                </a>
            @endif
        </div>
    </x-slot>

    @php($select = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs')

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-3">
            <form method="GET" action="{{ route('ventas.index') }}" class="p-4 bg-white shadow-sm sm:rounded-lg grid grid-cols-2 gap-3 sm:grid-cols-4 sm:items-end">
                <div>
                    <x-input-label for="desde" value="Desde" />
                    <x-text-input id="desde" name="desde" type="date" class="mt-1 block w-full" :value="$filtros['desde'] ?? ''" />
                </div>
                <div>
                    <x-input-label for="hasta" value="Hasta" />
                    <x-text-input id="hasta" name="hasta" type="date" class="mt-1 block w-full" :value="$filtros['hasta'] ?? ''" />
                    <x-input-error class="mt-1" :messages="$errors->get('hasta')" />
                </div>
                @if ($verTodas)
                    <div>
                        <x-input-label for="vendedor_id" value="Vendedor" />
                        <select id="vendedor_id" name="vendedor_id" class="{{ $select }}">
                            <option value="">Todos</option>
                            @foreach ($vendedores as $vendedor)
                                <option value="{{ $vendedor->id }}" @selected(($filtros['vendedor_id'] ?? null) == $vendedor->id)>{{ $vendedor->user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <x-input-label for="estado" value="Estado" />
                    <select id="estado" name="estado" class="{{ $select }}">
                        <option value="">Todos</option>
                        @foreach (App\Enums\EstadoVenta::cases() as $estado)
                            <option value="{{ $estado->value }}" @selected(($filtros['estado'] ?? null) === $estado->value)>{{ $estado->etiqueta() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-2 sm:col-span-4">
                    <x-primary-button>Filtrar</x-primary-button>
                </div>
            </form>

            <ul class="space-y-2" aria-label="Ventas, de la más reciente a la más antigua">
                @forelse ($ventas as $venta)
                    <li class="p-4 bg-white shadow-sm sm:rounded-lg flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900">
                                <a href="{{ route('ventas.show', $venta) }}" class="underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                    Venta N° {{ $venta->id }}
                                </a>
                                @if ($venta->comprobante)
                                    <span class="text-sm font-normal text-gray-600">· Comp. {{ $venta->comprobante->numeroCompleto() }}</span>
                                @endif
                                @if ($venta->estaAnulada())
                                    <span class="ms-1 rounded-sm bg-red-100 px-1.5 py-0.5 text-xs text-red-800">Anulada</span>
                                @endif
                            </p>
                            <p class="text-sm text-gray-600">
                                {{ $venta->fecha->format('d/m/Y') }}
                                · {{ $venta->cliente?->nombre ?? 'Consumidor final' }}
                                · {{ $venta->vendedor->user->name }}
                            </p>
                        </div>
                        <p class="font-semibold text-gray-900 {{ $venta->estaAnulada() ? 'line-through' : '' }}">$ {{ App\Services\Formato::importe($venta->total) }}</p>
                    </li>
                @empty
                    <li class="p-4 bg-white shadow-sm sm:rounded-lg text-gray-600">No hay ventas para los filtros elegidos.</li>
                @endforelse
            </ul>

            {{ $ventas->links() }}
        </div>
    </div>
</x-app-layout>
