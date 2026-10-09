{{-- Datos del producto, compartidos por el alta y la edición. Requiere $categorias, $marcas, $temporadas, $alicuotas y $producto (o null). --}}
@php($select = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs')

<div>
    <x-input-label for="nombre" value="Nombre" />
    <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" maxlength="150"
        :value="old('nombre', $producto?->nombre)" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('nombre')" />
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <x-input-label for="categoria_id" value="Categoría" />
        <select id="categoria_id" name="categoria_id" class="{{ $select }}" required>
            <option value="">Elegí una categoría</option>
            @foreach ($categorias as $categoria)
                <option value="{{ $categoria->id }}" @selected(old('categoria_id', $producto?->categoria_id) == $categoria->id)>{{ $categoria->nombre }}</option>
            @endforeach
        </select>
        @if ($categorias->isEmpty())
            <p class="mt-1 text-sm text-gray-600">No hay categorías activas. Creá una en <a class="underline" href="{{ route('catalogo.maestros.index', App\Enums\TablaMaestra::Categorias) }}">Categorías</a>.</p>
        @endif
        <x-input-error class="mt-2" :messages="$errors->get('categoria_id')" />
    </div>

    <div>
        <x-input-label for="alicuota_iva_id" value="Alícuota de IVA" />
        <select id="alicuota_iva_id" name="alicuota_iva_id" class="{{ $select }}" required>
            @foreach ($alicuotas as $alicuota)
                <option value="{{ $alicuota->id }}"
                    @selected(old('alicuota_iva_id', $producto?->alicuota_iva_id ?? ($alicuota->porcentaje === '21.00' ? $alicuota->id : null)) == $alicuota->id)>
                    {{ $alicuota->nombre }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('alicuota_iva_id')" />
    </div>

    <div>
        <x-input-label for="marca_id" value="Marca (opcional)" />
        <select id="marca_id" name="marca_id" class="{{ $select }}">
            <option value="">Sin marca</option>
            @foreach ($marcas as $marca)
                <option value="{{ $marca->id }}" @selected(old('marca_id', $producto?->marca_id) == $marca->id)>{{ $marca->nombre }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('marca_id')" />
    </div>

    <div>
        <x-input-label for="temporada_id" value="Temporada (opcional)" />
        <select id="temporada_id" name="temporada_id" class="{{ $select }}">
            <option value="">Sin temporada</option>
            @foreach ($temporadas as $temporada)
                <option value="{{ $temporada->id }}" @selected(old('temporada_id', $producto?->temporada_id) == $temporada->id)>{{ $temporada->nombre }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('temporada_id')" />
    </div>
</div>

<div>
    <x-input-label for="descripcion" value="Descripción (opcional)" />
    <textarea id="descripcion" name="descripcion" rows="3" maxlength="2000" class="{{ $select }}">{{ old('descripcion', $producto?->descripcion) }}</textarea>
    <x-input-error class="mt-2" :messages="$errors->get('descripcion')" />
</div>
