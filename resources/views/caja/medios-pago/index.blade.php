<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Medios de pago</h2>

            <a href="{{ route('caja.medios-pago.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Nuevo medio
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-3">
            @include('caja.partials.menu-configuracion')

            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            <ul class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100" aria-label="Medios de pago">
                @foreach ($medios as $medio)
                    <li class="flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="{{ $medio->activo ? 'text-gray-900' : 'text-gray-500 line-through' }}">
                                {{ $medio->nombre }}
                                @unless ($medio->activo)
                                    <span class="sr-only">(inactivo)</span>
                                @endunless
                            </p>
                            <p class="text-sm text-gray-600">
                                {{ $medio->tipo->etiqueta() }}
                                · {{ $medio->impacta_caja ? 'impacta en caja' : 'no impacta en caja' }}
                                @if ($medio->dias_acreditacion)
                                    · acredita en {{ $medio->dias_acreditacion }} {{ $medio->dias_acreditacion === 1 ? 'día' : 'días' }}
                                @endif
                                @if ($medio->requiere_referencia)
                                    · pide referencia
                                @endif
                                @if ($medio->planes_count)
                                    · {{ $medio->planes_count }} {{ $medio->planes_count === 1 ? 'plan' : 'planes' }}
                                @endif
                            </p>
                        </div>
                        <a href="{{ route('caja.medios-pago.edit', $medio) }}" class="shrink-0 text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                            Editar<span class="sr-only"> {{ $medio->nombre }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-app-layout>
