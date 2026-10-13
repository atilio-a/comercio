<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Orden N° {{ $orden->numero }}
                <span class="ms-1 rounded-sm px-1.5 py-0.5 text-xs align-middle {{ $orden->estado->clases() }}">{{ $orden->estado->etiqueta() }}</span>
            </h2>

            @if ($puedeRecibir && $orden->estado->recibible())
                <a href="{{ route('compras.recepciones.create', ['orden' => $orden->id]) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Recibir mercadería
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @include('compras.partials.menu')

            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            @if ($errors->has('orden') || $errors->anulacion->any())
                <div role="alert" class="p-4 bg-red-50 sm:rounded-lg">
                    <x-input-error :messages="array_merge($errors->get('orden'), $errors->anulacion->all())" />
                </div>
            @endif

            <section aria-labelledby="datos-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg space-y-1 text-sm text-gray-700">
                <h3 id="datos-titulo" class="sr-only">Datos de la orden</h3>
                <p class="text-base font-medium text-gray-900">{{ $orden->proveedor->razon_social }}</p>
                <p>
                    Fecha {{ $orden->fecha->format('d/m/Y') }} · cargó {{ $orden->user->name }}
                    @if ($orden->proveedor->condiciones_pago)
                        · {{ $orden->proveedor->condiciones_pago }}
                    @endif
                </p>
                @if ($orden->observaciones)
                    <p>{{ $orden->observaciones }}</p>
                @endif
                <p class="text-gray-900">Total pedido: <strong>$ {{ App\Services\Formato::importe(App\Services\Importes::aDecimal($orden->totalCentavos())) }}</strong></p>
            </section>

            <section aria-labelledby="items-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg space-y-2">
                <h3 id="items-titulo" class="font-semibold text-gray-900">Ítems</h3>
                <ul class="divide-y divide-gray-100">
                    @foreach ($orden->items as $item)
                        <li class="py-2 flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-gray-900">{{ $item->variante->producto->nombre }}</p>
                                <p class="text-sm text-gray-600">{{ $item->variante->descripcion() }} · {{ $item->variante->sku }} · $ {{ App\Services\Formato::importe($item->costo) }} c/u</p>
                            </div>
                            <p class="text-sm text-gray-700 text-end">
                                Pedido {{ $item->cantidad_pedida }} · recibido {{ $item->cantidad_recibida }}
                                @if ($item->pendiente() > 0 && $orden->estado !== App\Enums\EstadoOrdenCompra::Anulada)
                                    · <strong class="text-amber-700">pendiente {{ $item->pendiente() }}</strong>
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section aria-labelledby="recepciones-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg space-y-2">
                <h3 id="recepciones-titulo" class="font-semibold text-gray-900">Recepciones</h3>
                <ul class="divide-y divide-gray-100">
                    @forelse ($orden->recepciones as $recepcion)
                        <li class="py-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                            <a href="{{ route('compras.recepciones.show', $recepcion) }}" class="text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                Recepción N° {{ $recepcion->id }}
                            </a>
                            <span class="text-gray-700">
                                {{ $recepcion->fecha->format('d/m/Y') }} · {{ (int) $recepcion->unidades }} unidades · {{ $recepcion->user->name }}
                                @if ($recepcion->estaAnulada())
                                    · <strong class="text-red-700">Anulada</strong>
                                @endif
                            </span>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-gray-600">Sin recepciones.</li>
                    @endforelse
                </ul>
            </section>

            @if ($puedeGestionar && ($orden->estado->editable() || $orden->estado->anulable()))
                <section aria-labelledby="acciones-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg space-y-4">
                    <h3 id="acciones-titulo" class="font-semibold text-gray-900">Acciones</h3>

                    @if ($orden->estado->editable())
                        <div class="flex flex-wrap items-center gap-4">
                            <form method="POST" action="{{ route('compras.ordenes.emitir', $orden) }}">
                                @csrf
                                <x-primary-button>Emitir orden</x-primary-button>
                            </form>
                            <a href="{{ route('compras.ordenes.edit', $orden) }}" class="text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Editar borrador</a>
                        </div>
                    @endif

                    @if ($orden->estado->anulable())
                        <details @if ($errors->anulacion->any()) open @endif>
                            <summary class="cursor-pointer text-sm text-red-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Anular orden</summary>
                            <form method="POST" action="{{ route('compras.ordenes.anular', $orden) }}" class="mt-2 flex flex-wrap items-end gap-3">
                                @csrf
                                <div class="grow">
                                    <x-input-label for="motivo" value="Motivo de la anulación" />
                                    <x-text-input id="motivo" name="motivo" type="text" maxlength="200" class="mt-1 block w-full" :value="old('motivo')" required />
                                </div>
                                <x-danger-button>Anular</x-danger-button>
                            </form>
                        </details>
                    @endif
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
