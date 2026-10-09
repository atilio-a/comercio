<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $plan ? 'Editar plan' : 'Nuevo plan' }} · {{ $medio->nombre }}</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <form method="POST"
                action="{{ $plan ? route('caja.medios-pago.planes.update', [$medio, $plan]) : route('caja.medios-pago.planes.store', $medio) }}"
                class="p-4 sm:p-8 bg-white shadow-sm sm:rounded-lg space-y-6">
                @csrf
                @if ($plan)
                    @method('PUT')
                @endif

                <div>
                    <x-input-label for="cuotas" value="Cuotas" />
                    <x-text-input id="cuotas" name="cuotas" type="number" min="1" max="60" step="1" inputmode="numeric"
                        class="mt-1 block w-full" :value="old('cuotas', $plan?->cuotas)" required autofocus />
                    <x-input-error class="mt-2" :messages="$errors->get('cuotas')" />
                </div>

                <div>
                    <x-input-label for="recargo_pct" value="Recargo (%)" />
                    <x-text-input id="recargo_pct" name="recargo_pct" type="number" min="0" max="100" step="0.01" inputmode="decimal"
                        class="mt-1 block w-full" :value="old('recargo_pct', $plan?->recargo_pct ?? '0.00')" required aria-describedby="recargo-ayuda" />
                    <p id="recargo-ayuda" class="mt-1 text-sm text-gray-600">Se suma al total gravado de la venta y lleva IVA. Entre 0 y 100, con hasta dos decimales.</p>
                    <x-input-error class="mt-2" :messages="$errors->get('recargo_pct')" />
                </div>

                <div class="flex items-center gap-3">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" id="activo" name="activo" value="1"
                        class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500"
                        @checked(old('activo', $plan?->activo ?? true))>
                    <label for="activo" class="text-gray-900">Activo</label>
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <a href="{{ route('caja.medios-pago.edit', $medio) }}" class="text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
