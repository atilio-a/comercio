<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ajustar stock · {{ $producto->nombre }}</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('stock.ajuste.update', $producto) }}" class="p-4 sm:p-8 bg-white shadow-sm sm:rounded-lg space-y-6">
                @csrf
                @method('PUT')

                <p class="text-sm text-gray-600">
                    Ingresá la cantidad <strong>contada</strong> de cada variante. Se registra un ajuste por la diferencia con el stock actual.
                    Dejá el conteo vacío para no cambiar esa variante.
                </p>

                <x-input-error :messages="array_merge($errors->get('contado'), $errors->get('minimo'))" />

                <ul class="divide-y divide-gray-100" aria-label="Variantes">
                    @foreach ($variantes as $variante)
                        @php($actual = $stock->get($variante->id))
                        <li class="py-3 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:items-end">
                            <div class="col-span-2 sm:col-span-2">
                                <p class="font-medium text-gray-900">
                                    {{ $variante->descripcion() }}
                                    @unless ($variante->activo)
                                        <span class="ms-1 rounded-sm bg-red-100 px-1.5 py-0.5 text-xs text-red-800">Inactiva</span>
                                    @endunless
                                </p>
                                <p class="text-sm text-gray-600">Actual: <strong>{{ $actual?->cantidad ?? 0 }}</strong></p>
                            </div>
                            <div>
                                <x-input-label :for="'contado-'.$variante->id" value="Contado" />
                                <x-text-input :id="'contado-'.$variante->id" :name="'contado['.$variante->id.']'" type="number" min="0" step="1" inputmode="numeric"
                                    class="mt-1 block w-full" :value="old('contado.'.$variante->id)" />
                                <x-input-error class="mt-1" :messages="$errors->get('contado.'.$variante->id)" />
                            </div>
                            <div>
                                <x-input-label :for="'minimo-'.$variante->id" value="Mínimo" />
                                <x-text-input :id="'minimo-'.$variante->id" :name="'minimo['.$variante->id.']'" type="number" min="0" step="1" inputmode="numeric"
                                    class="mt-1 block w-full" :value="old('minimo.'.$variante->id, $actual?->minimo ?? 0)" />
                                <x-input-error class="mt-1" :messages="$errors->get('minimo.'.$variante->id)" />
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <x-input-label for="motivo" value="Motivo" />
                        <x-text-input id="motivo" name="motivo" type="text" maxlength="255" class="mt-1 block w-full"
                            :value="old('motivo')" placeholder="Stock inicial" aria-describedby="motivo-ayuda" />
                        <p id="motivo-ayuda" class="mt-1 text-sm text-gray-600">Obligatorio si cargás algún conteo. Queda en el kardex y en la auditoría.</p>
                        <x-input-error class="mt-1" :messages="$errors->get('motivo')" />
                    </div>
                    <div>
                        <x-input-label for="fecha" value="Fecha" />
                        <x-text-input id="fecha" name="fecha" type="date" class="mt-1 block w-full"
                            :value="old('fecha', now()->toDateString())" :max="now()->toDateString()" required />
                        <x-input-error class="mt-1" :messages="$errors->get('fecha')" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <x-primary-button>Registrar</x-primary-button>
                    <a href="{{ route('stock.index') }}" class="text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Volver al stock</a>
                    @can('view', $producto)
                        <a href="{{ route('catalogo.productos.show', $producto) }}" class="text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Ver producto</a>
                    @endcan
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
