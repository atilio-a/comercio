<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Alícuotas de IVA</h2>

            <a href="{{ route('catalogo.alicuotas.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Nueva alícuota
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-3">
            @include('catalogo.partials.menu-maestros')

            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            <ul class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100" aria-label="Alícuotas de IVA">
                @foreach ($alicuotas as $alicuota)
                    <li class="flex items-center justify-between gap-3 p-4">
                        <div class="{{ $alicuota->activo ? 'text-gray-900' : 'text-gray-500 line-through' }}">
                            {{ $alicuota->nombre }}
                            <span class="text-sm text-gray-600">· {{ App\Services\Formato::porcentaje($alicuota->porcentaje) }}</span>
                            @if ($alicuota->productos_count)
                                <span class="text-sm text-gray-600">· {{ $alicuota->productos_count }} productos</span>
                            @endif
                            @unless ($alicuota->activo)
                                <span class="sr-only">(inactiva)</span>
                            @endunless
                        </div>
                        <a href="{{ route('catalogo.alicuotas.edit', $alicuota) }}" class="text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                            Editar<span class="sr-only"> {{ $alicuota->nombre }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-app-layout>
