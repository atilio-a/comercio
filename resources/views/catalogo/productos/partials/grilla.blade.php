{{--
    Grilla color × talle para generar variantes. Requiere $colores y $usaTalles.
    El contador de Alpine es solo una ayuda: la validación real está en el servidor.
--}}
<fieldset x-data="grillaVariantes({{ Js::from(array_map('intval', (array) old('colores', []))) }}, {{ Js::from((string) old('talles', '')) }}, {{ Js::from($usaTalles) }})" class="space-y-4">
    <legend class="font-medium text-gray-900">Variantes</legend>

    <div>
        <p id="colores-ayuda" class="text-sm text-gray-600">Elegí los colores. Se crea una variante por cada color{{ $usaTalles ? ' y talle' : '' }}.</p>
        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3" role="group" aria-describedby="colores-ayuda">
            @foreach ($colores as $color)
                <label class="flex items-center gap-2 rounded-md border border-gray-300 p-2 has-checked:border-indigo-500 has-checked:bg-indigo-50">
                    <input type="checkbox" name="colores[]" value="{{ $color->id }}" x-model.number="colores"
                        class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500">
                    <span class="text-gray-900">{{ $color->nombre }}</span>
                </label>
            @endforeach
        </div>
        <x-input-error class="mt-2" :messages="array_merge($errors->get('colores'), $errors->get('colores.*'))" />
    </div>

    @if ($usaTalles)
        <div>
            <x-input-label for="talles" value="Talles" />
            <x-text-input id="talles" name="talles" type="text" class="mt-1 block w-full" maxlength="500"
                x-model="talles" placeholder="35-40" aria-describedby="talles-ayuda" required />
            <p id="talles-ayuda" class="mt-1 text-sm text-gray-600">Separados por coma; los rangos numéricos se expanden. Ejemplo: 35-38, 40 o S, M, L.</p>
            <x-input-error class="mt-2" :messages="$errors->get('talles')" />
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="costo" value="Costo" />
            <x-text-input id="costo" name="costo" type="number" step="0.01" min="0" inputmode="decimal"
                class="mt-1 block w-full" :value="old('costo')" required />
            <x-input-error class="mt-2" :messages="$errors->get('costo')" />
        </div>
        <div>
            <x-input-label for="precio_final" value="Precio final con IVA" />
            <x-text-input id="precio_final" name="precio_final" type="number" step="0.01" min="0.01" inputmode="decimal"
                class="mt-1 block w-full" :value="old('precio_final')" required />
            <x-input-error class="mt-2" :messages="$errors->get('precio_final')" />
        </div>
    </div>

    <p class="text-sm text-gray-700" aria-live="polite">
        Se van a crear <strong x-text="cantidad">0</strong> variantes.
        <span x-show="cantidad > 200" class="text-red-700">El máximo es 200.</span>
    </p>
</fieldset>
