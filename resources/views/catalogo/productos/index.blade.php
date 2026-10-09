<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Productos</h2>

            @can('create', App\Models\Producto::class)
                <a href="{{ route('catalogo.productos.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Nuevo producto
                </a>
            @endcan
        </div>
    </x-slot>

    @php($select = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs')

    <div class="py-6 sm:py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @can('catalogo.gestionar')
                @include('catalogo.partials.menu-maestros')
            @endcan

            <form method="GET" action="{{ route('catalogo.productos.index') }}" role="search"
                class="p-4 bg-white shadow-sm sm:rounded-lg grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                <div class="sm:col-span-2">
                    <x-input-label for="q" value="Buscar por nombre, SKU o código" />
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
                    <x-input-label for="marca_id" value="Marca" />
                    <select id="marca_id" name="marca_id" class="{{ $select }}">
                        <option value="">Todas</option>
                        @foreach ($marcas as $marca)
                            <option value="{{ $marca->id }}" @selected(($filtros['marca_id'] ?? null) == $marca->id)>{{ $marca->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="estado" value="Estado" />
                    <select id="estado" name="estado" class="{{ $select }}">
                        <option value="activos" @selected($filtros['estado'] === 'activos')>Activos</option>
                        <option value="inactivos" @selected($filtros['estado'] === 'inactivos')>Inactivos</option>
                        <option value="todos" @selected($filtros['estado'] === 'todos')>Todos</option>
                    </select>
                </div>
                <div class="sm:col-span-2 lg:col-span-5 flex gap-2">
                    <x-primary-button>Buscar</x-primary-button>
                    <a href="{{ route('catalogo.productos.index') }}" class="inline-flex items-center px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Limpiar</a>
                </div>
            </form>

            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            <ul class="space-y-3" aria-label="Productos">
                @forelse ($productos as $producto)
                    <li>
                        <a href="{{ route('catalogo.productos.show', $producto) }}"
                            class="flex items-center gap-3 p-4 bg-white shadow-sm sm:rounded-lg hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                            <x-foto-producto :foto="$producto->fotoPrincipal" :alt="$producto->nombre" class="h-16 w-16 shrink-0 rounded-md" />
                            <span class="min-w-0">
                            <span class="font-medium text-gray-900">{{ $producto->nombre }}</span>
                            @unless ($producto->activo)
                                <span class="ms-1 rounded-sm bg-red-100 px-1.5 py-0.5 text-xs text-red-800">Inactivo</span>
                            @endunless
                            <span class="block text-sm text-gray-600">
                                {{ $producto->categoria->nombre }}@if ($producto->marca) · {{ $producto->marca->nombre }}@endif
                                · {{ $producto->variantes_count }} variantes
                            </span>
                            </span>
                        </a>
                    </li>
                @empty
                    <li class="p-4 bg-white shadow-sm sm:rounded-lg text-gray-600">No se encontraron productos.</li>
                @endforelse
            </ul>

            {{ $productos->links() }}
        </div>
    </div>
</x-app-layout>
