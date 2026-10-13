{{-- Accesos entre las pantallas de compras. --}}
<nav aria-label="Compras" class="flex flex-wrap gap-2 px-4 sm:px-0">
    @foreach (['compras.ordenes.*' => ['compras.ordenes.index', 'Órdenes'], 'compras.recepciones.*' => ['compras.recepciones.index', 'Recepciones'], 'compras.proveedores.*' => ['compras.proveedores.index', 'Proveedores']] as $patron => [$ruta, $texto])
        @php($actual = request()->routeIs($patron))
        <a href="{{ route($ruta) }}"
            @if ($actual) aria-current="page" @endif
            class="rounded-full border px-3 py-1 text-sm focus:outline-hidden focus:ring-2 focus:ring-indigo-500 {{ $actual ? 'border-indigo-500 bg-indigo-50 text-indigo-800' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
            {{ $texto }}
        </a>
    @endforeach
</nav>
