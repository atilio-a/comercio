<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $tabla->plural() }}</h2>

            <a href="{{ route('catalogo.maestros.create', $tabla) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                {{ $tabla->tituloAlta() }}
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-3">
            @include('catalogo.partials.menu-maestros')

            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            <ul class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100" aria-label="{{ $tabla->plural() }}">
                @forelse ($registros as $registro)
                    <li class="flex items-center justify-between gap-3 p-4">
                        <span class="{{ $registro->activo ? 'text-gray-900' : 'text-gray-500 line-through' }}">
                            {{ $registro->nombre }}
                            @unless ($registro->activo)
                                <span class="sr-only">(inactivo)</span>
                            @endunless
                        </span>
                        <a href="{{ route('catalogo.maestros.edit', [$tabla, $registro->id]) }}" class="text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                            Editar<span class="sr-only"> {{ $registro->nombre }}</span>
                        </a>
                    </li>
                @empty
                    <li class="p-4 text-gray-600">Todavía no hay {{ mb_strtolower($tabla->plural()) }}.</li>
                @endforelse
            </ul>

            {{ $registros->links() }}
        </div>
    </div>
</x-app-layout>
