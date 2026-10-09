<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cerrar caja · {{ $sesion->caja->nombre }}</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('caja.cierre.update') }}" class="p-4 sm:p-8 bg-white shadow-sm sm:rounded-lg space-y-6">
                @csrf
                @method('PUT')

                <p class="text-sm text-gray-600">
                    Contá el dinero de cada medio que está en la caja y anotá lo contado. Si no coincide con lo esperado, explicá la diferencia.
                </p>

                <ul class="divide-y divide-gray-100" aria-label="Arqueo por medio de pago">
                    @foreach ($arqueo as $fila)
                        @php($medio = $fila['medio'])
                        <li class="py-3 grid grid-cols-2 gap-3 sm:items-end">
                            <div>
                                <p class="font-medium text-gray-900">{{ $medio->nombre }}</p>
                                <p class="text-sm text-gray-600">Esperado: <strong>$ {{ App\Services\Formato::importe($fila['esperado']) }}</strong></p>
                            </div>
                            @if ($fila['cuenta'])
                                <div>
                                    <x-input-label :for="'contado-'.$medio->id" value="Contado" />
                                    <x-text-input :id="'contado-'.$medio->id" :name="'contado['.$medio->id.']'" type="number" min="0" step="0.01" inputmode="decimal"
                                        class="mt-1 block w-full" :value="old('contado.'.$medio->id)" required />
                                    <x-input-error class="mt-1" :messages="$errors->get('contado.'.$medio->id)" />
                                </div>
                            @else
                                <p class="text-sm text-gray-600 self-center">No se cuenta: queda registrado el total.</p>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <div>
                    <x-input-label for="justificacion" value="Justificación de la diferencia" />
                    <textarea id="justificacion" name="justificacion" rows="3" maxlength="500" aria-describedby="justificacion-ayuda"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs">{{ old('justificacion') }}</textarea>
                    <p id="justificacion-ayuda" class="mt-1 text-sm text-gray-600">Obligatoria si lo contado no coincide con lo esperado. Queda en la auditoría.</p>
                    <x-input-error class="mt-1" :messages="$errors->get('justificacion')" />
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <x-primary-button>Cerrar caja</x-primary-button>
                    <a href="{{ route('caja.mi-caja') }}" class="text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Volver</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
