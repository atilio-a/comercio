<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cajas de la sucursal</h2>

            <a href="{{ route('caja.cajas.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Nueva caja
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-3">
            @include('caja.partials.menu-configuracion')

            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            <ul class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100" aria-label="Cajas">
                @forelse ($cajas as $caja)
                    <li class="flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="{{ $caja->activa ? 'text-gray-900' : 'text-gray-500 line-through' }}">
                                {{ $caja->nombre }}
                                @unless ($caja->activa)
                                    <span class="sr-only">(inactiva)</span>
                                @endunless
                            </p>
                            @if ($caja->sesionAbierta)
                                <p class="text-sm text-gray-600">Abierta por {{ $caja->sesionAbierta->user->name }}</p>
                            @endif
                        </div>
                        <a href="{{ route('caja.cajas.edit', $caja) }}" class="shrink-0 text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                            Editar<span class="sr-only"> {{ $caja->nombre }}</span>
                        </a>
                    </li>
                @empty
                    <li class="p-4 text-sm text-gray-600">La sucursal no tiene cajas.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-app-layout>
