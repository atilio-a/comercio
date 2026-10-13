<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Recepción N° {{ $recepcion->id }}
            @if ($recepcion->estaAnulada())
                <span class="ms-1 rounded-sm bg-red-100 px-1.5 py-0.5 text-xs align-middle text-red-800">Anulada</span>
            @endif
        </h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @include('compras.partials.menu')

            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            @if ($errors->anulacion->any())
                <div role="alert" class="p-4 bg-red-50 sm:rounded-lg">
                    <x-input-error :messages="$errors->anulacion->all()" />
                </div>
            @endif

            <section aria-labelledby="datos-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg space-y-1 text-sm text-gray-700">
                <h3 id="datos-titulo" class="sr-only">Datos de la recepción</h3>
                <p class="text-base font-medium text-gray-900">{{ $recepcion->proveedor->razon_social }}</p>
                <p>
                    Fecha {{ $recepcion->fecha->format('d/m/Y') }} · registrada {{ $recepcion->created_at->format('d/m/Y H:i') }} por {{ $recepcion->user->name }}
                </p>
                <p>
                    @if ($recepcion->orden)
                        <a href="{{ route('compras.ordenes.show', $recepcion->orden) }}" class="text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Orden N° {{ $recepcion->orden->numero }}</a>
                    @else
                        Sin orden
                    @endif
                    @if ($recepcion->referencia)
                        · Ref. {{ $recepcion->referencia }}
                    @endif
                </p>
                @if ($recepcion->observaciones)
                    <p>{{ $recepcion->observaciones }}</p>
                @endif
                @if ($recepcion->estaAnulada())
                    <p class="text-red-800">
                        Anulada el {{ $recepcion->anulada_at->format('d/m/Y H:i') }} por {{ $recepcion->anuladaPor?->name ?? 'Sistema' }}: {{ $recepcion->motivo_anulacion }}
                    </p>
                @endif
            </section>

            <section aria-labelledby="items-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg space-y-2">
                <h3 id="items-titulo" class="font-semibold text-gray-900">Ítems</h3>
                <ul class="divide-y divide-gray-100">
                    @foreach ($recepcion->items as $item)
                        <li class="py-2 flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-gray-900">{{ $item->variante->producto->nombre }}</p>
                                <p class="text-sm text-gray-600">
                                    {{ $item->variante->descripcion() }} · {{ $item->variante->sku }} · $ {{ App\Services\Formato::importe($item->costo) }} c/u
                                    @if ($item->actualiza_costo)
                                        · actualizó el costo
                                    @endif
                                </p>
                            </div>
                            <div class="text-end text-sm">
                                <p class="text-lg font-semibold text-gray-900">{{ $item->cantidad }}</p>
                                @can('stock.ver')
                                    <a href="{{ route('stock.kardex', $item->variante_id) }}" class="text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                        Kardex<span class="sr-only"> de {{ $item->variante->producto->nombre }} {{ $item->variante->descripcion() }}</span>
                                    </a>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>

            @if ($puedeAnular)
                <section aria-labelledby="anular-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg">
                    <details @if ($errors->anulacion->any()) open @endif>
                        <summary id="anular-titulo" class="cursor-pointer text-sm text-red-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Anular recepción</summary>
                        <p class="mt-2 text-sm text-gray-700">
                            Registra egresos de stock por todo lo recibido{{ $recepcion->orden ? ' y lo vuelve a dejar pendiente en la orden' : '' }}.
                            No revierte los costos actualizados.
                        </p>
                        <form method="POST" action="{{ route('compras.recepciones.anular', $recepcion) }}" class="mt-2 flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="grow">
                                <x-input-label for="motivo" value="Motivo de la anulación" />
                                <x-text-input id="motivo" name="motivo" type="text" maxlength="200" class="mt-1 block w-full" :value="old('motivo')" required />
                            </div>
                            <x-danger-button>Anular recepción</x-danger-button>
                        </form>
                    </details>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
