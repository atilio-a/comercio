<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Recepciones</h2>

            @if ($puedeRecibir)
                <a href="{{ route('compras.recepciones.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Recepción sin orden
                </a>
            @endif
        </div>
    </x-slot>

    @php($select = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs')

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-3">
            @include('compras.partials.menu')

            @if ($puedeRecibir)
                <p class="px-4 sm:px-0 text-sm text-gray-600">Para recibir una orden, abrila desde <a href="{{ route('compras.ordenes.index', ['estado' => 'emitida']) }}" class="text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Órdenes</a> y elegí "Recibir mercadería".</p>
            @endif

            <form method="GET" action="{{ route('compras.recepciones.index') }}" class="p-4 bg-white shadow-sm sm:rounded-lg grid grid-cols-1 gap-3 sm:grid-cols-4 sm:items-end">
                <div class="sm:col-span-2">
                    <x-input-label for="proveedor_id" value="Proveedor" />
                    <select id="proveedor_id" name="proveedor_id" class="{{ $select }}">
                        <option value="">Todos</option>
                        @foreach ($proveedores as $proveedor)
                            <option value="{{ $proveedor->id }}" @selected(($filtros['proveedor_id'] ?? null) == $proveedor->id)>{{ $proveedor->razon_social }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="desde" value="Desde" />
                    <x-text-input id="desde" name="desde" type="date" class="mt-1 block w-full" :value="$filtros['desde'] ?? ''" />
                </div>
                <div>
                    <x-input-label for="hasta" value="Hasta" />
                    <x-text-input id="hasta" name="hasta" type="date" class="mt-1 block w-full" :value="$filtros['hasta'] ?? ''" />
                    <x-input-error class="mt-1" :messages="$errors->get('hasta')" />
                </div>
                <div class="sm:col-span-4">
                    <x-primary-button>Filtrar</x-primary-button>
                </div>
            </form>

            <ul class="space-y-2" aria-label="Recepciones">
                @forelse ($recepciones as $recepcion)
                    <li class="p-4 bg-white shadow-sm sm:rounded-lg flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900">
                                <a href="{{ route('compras.recepciones.show', $recepcion) }}" class="underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Recepción N° {{ $recepcion->id }}</a>
                                @if ($recepcion->estaAnulada())
                                    <span class="ms-1 rounded-sm bg-red-100 px-1.5 py-0.5 text-xs text-red-800">Anulada</span>
                                @endif
                            </p>
                            <p class="text-sm text-gray-600">
                                {{ $recepcion->proveedor->razon_social }} · {{ $recepcion->fecha->format('d/m/Y') }}
                                · {{ $recepcion->orden ? 'Orden N° '.$recepcion->orden->numero : 'Sin orden' }}
                                @if ($recepcion->referencia)
                                    · Ref. {{ $recepcion->referencia }}
                                @endif
                            </p>
                        </div>
                        <p class="text-sm text-gray-700">{{ (int) $recepcion->unidades }} unidades · {{ $recepcion->user->name }}</p>
                    </li>
                @empty
                    <li class="p-4 bg-white shadow-sm sm:rounded-lg text-gray-600">No hay recepciones para los filtros elegidos.</li>
                @endforelse
            </ul>

            {{ $recepciones->links() }}
        </div>
    </div>
</x-app-layout>
