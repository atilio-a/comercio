<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Stock</h2>
    </x-slot>

    @php($select = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs')
    @php($puedeAjustar = auth()->user()->can('stock.ajustar'))

    <div class="py-6 sm:py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('stock.index') }}" role="search"
                class="p-4 bg-white shadow-sm sm:rounded-lg grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                <div class="sm:col-span-2">
                    <x-input-label for="q" value="Buscar por producto, SKU o código" />
                    <x-text-input id="q" name="q" type="search" class="mt-1 block w-full" :value="$filtros['q'] ?? ''" autocomplete="off" />
                </div>
                <div>
                    <x-input-label for="categoria_id" value="Categoría" />
                    <select id="categoria_id" name="categoria_id" class="{{ $select }}">
                        <option value="">Todas</option>
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria->id }}" @selected(($filtros['categoria_id'] ?? null) == $categoria->id)>{{ $categoria->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="estado" value="Variantes" />
                    <select id="estado" name="estado" class="{{ $select }}">
                        <option value="vendibles" @selected($filtros['estado'] === 'vendibles')>Vendibles</option>
                        <option value="todas" @selected($filtros['estado'] === 'todas')>Todas</option>
                    </select>
                </div>
                <div class="sm:col-span-2 lg:col-span-4 flex flex-wrap items-center gap-4">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="bajo_minimo" value="1" @checked($filtros['bajo_minimo'])
                            class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500">
                        <span class="text-sm text-gray-900">Solo bajo mínimo</span>
                    </label>
                    <x-primary-button>Buscar</x-primary-button>
                    <a href="{{ route('stock.index') }}" class="text-xs font-semibold uppercase tracking-widest text-gray-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Limpiar</a>
                </div>
            </form>

            <ul class="space-y-2" aria-label="Stock por variante">
                @forelse ($variantes as $variante)
                    @php($negativo = $variante->stock_cantidad < 0)
                    @php($bajo = $variante->stock_minimo > 0 && $variante->stock_cantidad <= $variante->stock_minimo)
                    <li class="p-4 bg-white shadow-sm sm:rounded-lg flex flex-wrap items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <x-foto-producto :foto="$variante->producto->fotoPrincipal" :alt="$variante->producto_nombre" class="h-12 w-12 shrink-0 rounded-md" />
                            <div class="min-w-0">
                            <p class="font-medium text-gray-900">{{ $variante->producto_nombre }}</p>
                            <p class="text-sm text-gray-600">{{ $variante->descripcion() }} · {{ $variante->sku }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <p class="text-end">
                                <span class="block text-2xl font-semibold {{ $negativo ? 'text-red-700' : ($bajo ? 'text-amber-700' : 'text-gray-900') }}">
                                    {{ $variante->stock_cantidad }}
                                </span>
                                <span class="block text-xs text-gray-600">
                                    @if ($negativo)
                                        <strong class="text-red-700">Stock negativo</strong> ·
                                    @elseif ($bajo)
                                        <strong class="text-amber-700">Bajo mínimo</strong> ·
                                    @endif
                                    mín. {{ $variante->stock_minimo }}
                                </span>
                            </p>
                            <div class="flex flex-col gap-1 text-sm">
                                <a href="{{ route('stock.kardex', $variante) }}" class="text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                    Kardex<span class="sr-only"> de {{ $variante->producto_nombre }} {{ $variante->descripcion() }}</span>
                                </a>
                                @if ($puedeAjustar)
                                    <a href="{{ route('stock.ajuste.edit', $variante->producto_id) }}" class="text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                        Ajustar<span class="sr-only"> {{ $variante->producto_nombre }}</span>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="p-4 bg-white shadow-sm sm:rounded-lg text-gray-600">No hay variantes para los filtros elegidos.</li>
                @endforelse
            </ul>

            {{ $variantes->links() }}
        </div>
    </div>
</x-app-layout>
