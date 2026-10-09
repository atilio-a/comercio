<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $producto->nombre }} · {{ $variante->descripcion() }}</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('catalogo.productos.variantes.update', [$producto, $variante]) }}" class="p-4 sm:p-8 bg-white shadow-sm sm:rounded-lg space-y-6">
                @csrf
                @method('PUT')

                <p class="text-sm text-gray-600">El color y el talle identifican la variante y no se pueden cambiar.</p>

                <div>
                    <x-input-label for="sku" value="SKU" />
                    <x-text-input id="sku" name="sku" type="text" class="mt-1 block w-full" maxlength="50" autocomplete="off"
                        :value="old('sku', $variante->sku)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('sku')" />
                </div>

                <div>
                    <x-input-label for="codigo_barras" value="Código de barras" />
                    <x-text-input id="codigo_barras" name="codigo_barras" type="text" class="mt-1 block w-full" maxlength="50"
                        autocomplete="off" inputmode="text" aria-describedby="codigo-ayuda"
                        :value="old('codigo_barras', $variante->codigo_barras)" required />
                    <p id="codigo-ayuda" class="mt-1 text-sm text-gray-600">Podés escribirlo o escanearlo con un lector de código de barras.</p>
                    <x-input-error class="mt-2" :messages="$errors->get('codigo_barras')" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="costo" value="Costo" />
                        <x-text-input id="costo" name="costo" type="number" step="0.01" min="0" inputmode="decimal"
                            class="mt-1 block w-full" :value="old('costo', $variante->costo)" required />
                        <x-input-error class="mt-2" :messages="$errors->get('costo')" />
                    </div>
                    <div>
                        <x-input-label for="precio_final" value="Precio final con IVA" />
                        <x-text-input id="precio_final" name="precio_final" type="number" step="0.01" min="0.01" inputmode="decimal"
                            class="mt-1 block w-full" :value="old('precio_final', $variante->precio_final)" required aria-describedby="desglose" />
                        <p id="desglose" class="mt-1 text-sm text-gray-600">
                            Actual: neto $ {{ App\Services\Formato::importe($desglose['neto']) }} + IVA $ {{ App\Services\Formato::importe($desglose['iva']) }}
                            ({{ App\Services\Formato::porcentaje($producto->alicuotaIva->porcentaje) }}).
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('precio_final')" />
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" id="activo" name="activo" value="1"
                        class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500"
                        @checked(old('activo', $variante->activo))>
                    <label for="activo" class="text-gray-900">Activa</label>
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <a href="{{ route('catalogo.productos.show', $producto) }}" class="text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
