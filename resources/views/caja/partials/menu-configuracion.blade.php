{{-- Accesos entre las pantallas de configuración de caja. --}}
<nav aria-label="Configuración de caja" class="flex flex-wrap gap-2">
    @foreach (['caja.medios-pago.*' => ['caja.medios-pago.index', 'Medios de pago'], 'caja.cajas.*' => ['caja.cajas.index', 'Cajas de la sucursal']] as $patron => [$ruta, $texto])
        @php($actual = request()->routeIs($patron))
        <a href="{{ route($ruta) }}"
            @if ($actual) aria-current="page" @endif
            class="rounded-full border px-3 py-1 text-sm focus:outline-hidden focus:ring-2 focus:ring-indigo-500 {{ $actual ? 'border-indigo-500 bg-indigo-50 text-indigo-800' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
            {{ $texto }}
        </a>
    @endforeach
</nav>
