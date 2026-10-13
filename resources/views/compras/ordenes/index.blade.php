<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Órdenes de compra</h2>

            @if ($puedeGestionar)
                <a href="{{ route('compras.ordenes.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Nueva orden
                </a>
            @endif
        </div>
    </x-slot>

    @php($select = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs')

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-3">
            @include('compras.partials.menu')

            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            <form method="GET" action="{{ route('compras.ordenes.index') }}" class="p-4 bg-white shadow-sm sm:rounded-lg grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-end">
                <div>
                    <x-input-label for="estado" value="Estado" />
                    <select id="estado" name="estado" class="{{ $select }}">
                        <option value="">Todos</option>
                        @foreach (App\Enums\EstadoOrdenCompra::cases() as $estado)
                            <option value="{{ $estado->value }}" @selected(($filtros['estado'] ?? null) === $estado->value)>{{ $estado->etiqueta() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="proveedor_id" value="Proveedor" />
                    <select id="proveedor_id" name="proveedor_id" class="{{ $select }}">
                        <option value="">Todos</option>
                        @foreach ($proveedores as $proveedor)
                            <option value="{{ $proveedor->id }}" @selected(($filtros['proveedor_id'] ?? null) == $proveedor->id)>{{ $proveedor->razon_social }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-primary-button>Filtrar</x-primary-button>
                </div>
            </form>

            <ul class="space-y-2" aria-label="Órdenes de compra">
                @forelse ($ordenes as $orden)
                    <li class="p-4 bg-white shadow-sm sm:rounded-lg flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900">
                                <a href="{{ route('compras.ordenes.show', $orden) }}" class="underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Orden N° {{ $orden->numero }}</a>
                                <span class="ms-1 rounded-sm px-1.5 py-0.5 text-xs {{ $orden->estado->clases() }}">{{ $orden->estado->etiqueta() }}</span>
                            </p>
                            <p class="text-sm text-gray-600">{{ $orden->proveedor->razon_social }} · {{ $orden->fecha->format('d/m/Y') }}</p>
                        </div>
                        <p class="text-sm text-gray-700">{{ (int) $orden->unidades_recibidas }} de {{ (int) $orden->unidades_pedidas }} unidades recibidas</p>
                    </li>
                @empty
                    <li class="p-4 bg-white shadow-sm sm:rounded-lg text-gray-600">No hay órdenes para los filtros elegidos.</li>
                @endforelse
            </ul>

            {{ $ordenes->links() }}
        </div>
    </div>
</x-app-layout>
