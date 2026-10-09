{{-- Galería de imágenes del producto. Requiere $producto, $fotos y $coloresProducto. --}}
@php($select = 'block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs text-sm')

<section id="imagenes" aria-labelledby="imagenes-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg space-y-4">
    <h3 id="imagenes-titulo" class="font-medium text-gray-900">Imágenes ({{ $fotos->count() }}/{{ App\Services\GaleriaProducto::MAXIMO_POR_PRODUCTO }})</h3>

    @if ($fotos->isEmpty())
        <p class="text-sm text-gray-600">Este producto todavía no tiene imágenes.</p>
    @else
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" aria-label="Imágenes del producto">
            @foreach ($fotos as $foto)
                <li class="rounded-lg border {{ $foto->es_principal ? 'border-indigo-500 ring-2 ring-indigo-200' : 'border-gray-200' }} overflow-hidden">
                    <a href="{{ route('catalogo.fotos.mostrar', [$foto, 'original']) }}" target="_blank" rel="noopener"
                        class="block focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                        <x-foto-producto :foto="$foto" class="aspect-square w-full" />
                        <span class="sr-only">Ver imagen completa (se abre en otra pestaña)</span>
                    </a>
                    <div class="p-2 space-y-2 text-sm">
                        <p class="flex flex-wrap gap-1">
                            @if ($foto->es_principal)
                                <span class="rounded-sm bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-800">Principal</span>
                            @endif
                            <span class="text-gray-600">{{ $foto->color?->nombre ?? 'Todos los colores' }}</span>
                        </p>

                        @can('update', $producto)
                            @unless ($foto->es_principal)
                                <form method="POST" action="{{ route('catalogo.productos.fotos.principal', [$producto, $foto]) }}">
                                    @csrf
                                    @method('PUT')
                                    <button type="submit" class="text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Hacer principal</button>
                                </form>
                            @endunless

                            <form method="POST" action="{{ route('catalogo.productos.fotos.color', [$producto, $foto]) }}" class="flex gap-1">
                                @csrf
                                @method('PUT')
                                <label for="color-foto-{{ $foto->id }}" class="sr-only">Color de la imagen</label>
                                <select id="color-foto-{{ $foto->id }}" name="color_id" class="{{ $select }}">
                                    <option value="">Todos los colores</option>
                                    @foreach ($coloresProducto as $color)
                                        <option value="{{ $color->id }}" @selected($foto->color_id === $color->id)>{{ $color->nombre }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="shrink-0 px-2 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-indigo-500">OK</button>
                            </form>

                            <form method="POST" action="{{ route('catalogo.productos.fotos.destroy', [$producto, $foto]) }}"
                                onsubmit="return confirm('¿Eliminar esta imagen? No se puede deshacer.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-red-500">Eliminar</button>
                            </form>
                        @endcan
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @can('update', $producto)
        @if ($fotos->count() < App\Services\GaleriaProducto::MAXIMO_POR_PRODUCTO)
            <form method="POST" action="{{ route('catalogo.productos.fotos.store', $producto) }}" enctype="multipart/form-data"
                x-data="subirFotos()" class="space-y-3 border-t border-gray-100 pt-4">
                @csrf

                <div>
                    <x-input-label for="fotos" value="Agregar imágenes" />
                    <input id="fotos" name="fotos[]" type="file" multiple accept="image/jpeg,image/png,image/webp"
                        @change="elegir($event)" aria-describedby="fotos-ayuda"
                        class="mt-1 block w-full text-sm text-gray-700 file:me-3 file:rounded-md file:border-0 file:bg-gray-800 file:px-4 file:py-2 file:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                    <input type="file" name="miniaturas[]" multiple x-ref="miniaturas" class="hidden" tabindex="-1" aria-hidden="true">
                    <p id="fotos-ayuda" class="mt-1 text-sm text-gray-600">JPEG, PNG o WebP. Desde el celular podés usar la cámara. Las fotos se achican antes de subirlas.</p>
                    <x-input-error class="mt-1" :messages="array_merge($errors->get('fotos'), $errors->get('fotos.*'), $errors->get('miniaturas.*'))" />
                </div>

                <ul x-show="previas.length" class="flex flex-wrap gap-2" aria-label="Imágenes elegidas" x-cloak>
                    <template x-for="previa in previas" :key="previa.url">
                        <li class="w-20">
                            <img :src="previa.url" :alt="previa.nombre" class="h-20 w-20 rounded-md object-cover">
                            <span class="block text-xs text-gray-600" x-text="previa.kb + ' KB'"></span>
                        </li>
                    </template>
                </ul>

                <div>
                    <x-input-label for="color_id_nuevas" value="Color (opcional)" />
                    <select id="color_id_nuevas" name="color_id" class="mt-1 {{ $select }} sm:w-64">
                        <option value="">Todos los colores</option>
                        @foreach ($coloresProducto as $color)
                            <option value="{{ $color->id }}" @selected(old('color_id') == $color->id)>{{ $color->nombre }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('color_id')" />
                </div>

                <x-primary-button x-bind:disabled="procesando" class="disabled:opacity-50">
                    <span x-show="!procesando">Subir imágenes</span>
                    <span x-show="procesando" x-cloak>Procesando…</span>
                </x-primary-button>
            </form>
        @endif
    @endcan
</section>
