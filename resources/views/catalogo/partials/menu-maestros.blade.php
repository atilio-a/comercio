{{-- Accesos entre las tablas maestras del catálogo. --}}
<nav aria-label="Tablas del catálogo" class="flex flex-wrap gap-2">
    @foreach (App\Enums\TablaMaestra::cases() as $opcion)
        @php($actual = request()->route('tabla') === $opcion)
        <a href="{{ route('catalogo.maestros.index', $opcion) }}"
            @if ($actual) aria-current="page" @endif
            class="rounded-full border px-3 py-1 text-sm focus:outline-hidden focus:ring-2 focus:ring-indigo-500 {{ $actual ? 'border-indigo-500 bg-indigo-50 text-indigo-800' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
            {{ $opcion->plural() }}
        </a>
    @endforeach
    @php($actual = request()->routeIs('catalogo.alicuotas.*'))
    <a href="{{ route('catalogo.alicuotas.index') }}"
        @if ($actual) aria-current="page" @endif
        class="rounded-full border px-3 py-1 text-sm focus:outline-hidden focus:ring-2 focus:ring-indigo-500 {{ $actual ? 'border-indigo-500 bg-indigo-50 text-indigo-800' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
        Alícuotas de IVA
    </a>
</nav>
