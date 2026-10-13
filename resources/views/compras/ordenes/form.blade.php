<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $orden ? "Editar orden N° {$orden->numero}" : 'Nueva orden de compra' }}</h2>
    </x-slot>

    @php($select = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs')

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($errors->has('orden'))
                <div role="alert" class="p-4 bg-red-50 sm:rounded-lg">
                    <x-input-error :messages="$errors->get('orden')" />
                </div>
            @endif

            <form method="POST"
                action="{{ $orden ? route('compras.ordenes.update', $orden) : route('compras.ordenes.store') }}"
                class="p-4 sm:p-8 bg-white shadow-sm sm:rounded-lg space-y-6">
                @csrf
                @if ($orden)
                    @method('PUT')
                @endif

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <x-input-label for="proveedor_id" value="Proveedor" />
                        <select id="proveedor_id" name="proveedor_id" class="{{ $select }}" required>
                            <option value="">Elegí un proveedor</option>
                            @foreach ($proveedores as $proveedor)
                                <option value="{{ $proveedor->id }}" @selected((int) old('proveedor_id', $orden?->proveedor_id) === $proveedor->id)>{{ $proveedor->razon_social }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('proveedor_id')" />
                    </div>
                    <div>
                        <x-input-label for="fecha" value="Fecha" />
                        <x-text-input id="fecha" name="fecha" type="date" class="mt-1 block w-full"
                            :value="old('fecha', $orden?->fecha?->toDateString() ?? now()->toDateString())" :max="now()->toDateString()" required />
                        <x-input-error class="mt-2" :messages="$errors->get('fecha')" />
                    </div>
                </div>

                @include('compras.partials.items', ['items' => $items, 'modo' => 'orden'])

                <div>
                    <x-input-label for="observaciones" value="Observaciones" />
                    <textarea id="observaciones" name="observaciones" rows="2" maxlength="500" class="{{ $select }}">{{ old('observaciones', $orden?->observaciones) }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('observaciones')" />
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <x-primary-button>Guardar borrador</x-primary-button>
                    <a href="{{ $orden ? route('compras.ordenes.show', $orden) : route('compras.ordenes.index') }}" class="text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
