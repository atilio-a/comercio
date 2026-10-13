{{--
    Editor de ítems de órdenes y recepciones (componente Alpine itemsCompra).
    Parámetros: $items (lista de arrays), $modo ('orden', 'libre' o 'contraOrden').
--}}
@php
    $erroresItems = collect($errors->getMessages())->filter(fn ($mensajes, $clave) => str_starts_with($clave, 'items.'))->map(fn ($mensajes) => $mensajes[0]);
    $conCosto = $modo !== 'orden';
@endphp

<section x-data="itemsCompra({{ Js::from(['items' => $items, 'modo' => $modo, 'urlBuscar' => route('compras.variantes.buscar'), 'errores' => (object) $erroresItems->all()]) }})"
    aria-labelledby="items-titulo" class="space-y-4">
    <h3 id="items-titulo" class="font-semibold text-gray-900">Ítems</h3>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <x-input-label for="escaner" value="Escanear código" />
            <div class="mt-1 flex gap-2">
                <x-text-input id="escaner" type="text" inputmode="numeric" autocomplete="off" class="block w-full"
                    x-model="codigo" x-on:keydown.enter.prevent="escanear()" aria-describedby="escaner-ayuda" />
                <x-secondary-button type="button" x-on:click="escanear()">Sumar</x-secondary-button>
            </div>
            <p id="escaner-ayuda" class="mt-1 text-sm text-gray-600">Código de barras o SKU exacto. Cada lectura suma una unidad.</p>
        </div>

        <template x-if="agregaItems">
            <div class="relative">
                <x-input-label for="buscar-variante" value="Buscar por nombre, SKU o código" />
                <x-text-input id="buscar-variante" type="search" autocomplete="off" class="mt-1 block w-full"
                    x-model="busqueda" x-on:input="buscar()" x-on:keydown.enter.prevent="resultados.length === 1 && agregar(resultados[0])"
                    aria-controls="resultados-variantes" />
                <ul id="resultados-variantes" x-show="resultados.length > 0" x-cloak
                    class="mt-1 max-h-72 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg divide-y divide-gray-100" aria-label="Variantes encontradas">
                    <template x-for="variante in resultados" :key="variante.variante_id">
                        <li>
                            <button type="button" x-on:click="agregar(variante)"
                                class="block w-full px-3 py-2 text-start hover:bg-gray-50 focus:outline-hidden focus:bg-indigo-50 focus:ring-2 focus:ring-inset focus:ring-indigo-500">
                                <span class="block text-gray-900" x-text="variante.producto"></span>
                                <span class="block text-sm text-gray-600" x-text="`${variante.descripcion} · ${variante.sku}`"></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>
        </template>
    </div>

    <p class="text-sm text-gray-700" role="status" aria-live="polite" x-text="mensaje"></p>

    @if ($modo === 'contraOrden')
        <div class="flex flex-wrap gap-2">
            <x-secondary-button type="button" x-on:click="recibirTodo()">Recibir todo lo pendiente</x-secondary-button>
            <x-secondary-button type="button" x-on:click="ponerEnCero()">Poner todo en 0</x-secondary-button>
        </div>
    @endif

    <x-input-error :messages="$errors->get('items')" />

    <ul class="divide-y divide-gray-100" aria-label="Ítems cargados">
        <template x-for="(item, indice) in items" :key="item.variante_id">
            <li class="py-3 grid grid-cols-2 gap-3 sm:grid-cols-6 sm:items-end">
                <input type="hidden" :name="`items[${indice}][variante_id]`" :value="item.variante_id">
                <div class="col-span-2 sm:col-span-3 min-w-0">
                    <p class="font-medium text-gray-900" x-text="item.producto"></p>
                    <p class="text-sm text-gray-600">
                        <span x-text="`${item.descripcion} · ${item.sku}`"></span>
                        <template x-if="item.pendiente !== undefined">
                            <span> · pendiente <strong x-text="item.pendiente"></strong></span>
                        </template>
                    </p>
                    <p class="text-sm text-red-600" x-show="errores[`items.${indice}.variante_id`]" x-text="errores[`items.${indice}.variante_id`]"></p>
                </div>
                <div>
                    <label :for="`cantidad-${indice}`" class="block font-medium text-sm text-gray-700">Cantidad</label>
                    <input :id="`cantidad-${indice}`" :name="`items[${indice}][cantidad]`" type="number" min="{{ $modo === 'contraOrden' ? 0 : 1 }}" step="1" inputmode="numeric" required
                        x-model="item.cantidad" :max="item.pendiente"
                        :aria-invalid="(excede(item) || !!errores[`items.${indice}.cantidad`]).toString()"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs">
                    <p class="mt-1 text-sm text-amber-700" x-show="excede(item)">Supera lo pendiente.</p>
                    <p class="mt-1 text-sm text-red-600" x-show="errores[`items.${indice}.cantidad`]" x-text="errores[`items.${indice}.cantidad`]"></p>
                </div>
                <div>
                    <label :for="`costo-${indice}`" class="block font-medium text-sm text-gray-700">Costo unit.</label>
                    <input :id="`costo-${indice}`" :name="`items[${indice}][costo]`" type="number" min="0" step="0.01" inputmode="decimal" required
                        x-model="item.costo"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs">
                    <p class="mt-1 text-sm text-red-600" x-show="errores[`items.${indice}.costo`]" x-text="errores[`items.${indice}.costo`]"></p>
                </div>
                <div class="col-span-2 sm:col-span-1 flex flex-wrap items-center gap-3">
                    @if ($conCosto)
                        <label class="inline-flex items-center gap-2 text-sm text-gray-900">
                            <input type="checkbox" value="1" :name="`items[${indice}][actualizar_costo]`" x-model="item.actualizar_costo"
                                class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500">
                            Actualizar costo
                        </label>
                    @endif
                    <template x-if="agregaItems">
                        <button type="button" x-on:click="quitar(indice)" class="text-sm text-red-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                            Quitar<span class="sr-only" x-text="` ${item.producto} ${item.descripcion}`"></span>
                        </button>
                    </template>
                </div>
            </li>
        </template>
        <li x-show="items.length === 0" class="py-3 text-sm text-gray-600">Todavía no hay ítems. Escaneá un código o buscá una variante.</li>
    </ul>

    <p class="text-end text-gray-900">
        <span x-text="unidades"></span> unidades · total $ <strong x-text="total"></strong>
    </p>
</section>
