<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $medio ? 'Editar medio de pago' : 'Nuevo medio de pago' }}</h2>
    </x-slot>

    @php($select = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs')
    @php($checkbox = 'rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500')

    <div class="py-6 sm:py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            <form method="POST"
                action="{{ $medio ? route('caja.medios-pago.update', $medio) : route('caja.medios-pago.store') }}"
                class="p-4 sm:p-8 bg-white shadow-sm sm:rounded-lg space-y-6">
                @csrf
                @if ($medio)
                    @method('PUT')
                @endif

                <div>
                    <x-input-label for="nombre" value="Nombre" />
                    <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" maxlength="50"
                        :value="old('nombre', $medio?->nombre)" required autofocus />
                    <x-input-error class="mt-2" :messages="$errors->get('nombre')" />
                </div>

                <div>
                    <x-input-label for="tipo" value="Tipo" />
                    <select id="tipo" name="tipo" class="{{ $select }}" required>
                        @foreach (App\Enums\TipoMedioPago::cases() as $tipo)
                            <option value="{{ $tipo->value }}" @selected(old('tipo', $medio?->tipo?->value) === $tipo->value)>{{ $tipo->etiqueta() }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('tipo')" />
                </div>

                <div>
                    <x-input-label for="dias_acreditacion" value="Días de acreditación" />
                    <x-text-input id="dias_acreditacion" name="dias_acreditacion" type="number" min="0" max="365" step="1" inputmode="numeric"
                        class="mt-1 block w-full" :value="old('dias_acreditacion', $medio?->dias_acreditacion ?? 0)" required aria-describedby="dias-ayuda" />
                    <p id="dias-ayuda" class="mt-1 text-sm text-gray-600">Cuántos días tarda en acreditarse el cobro (0 si es inmediato).</p>
                    <x-input-error class="mt-2" :messages="$errors->get('dias_acreditacion')" />
                </div>

                <div class="space-y-3">
                    <div class="flex items-start gap-3">
                        <input type="hidden" name="impacta_caja" value="0">
                        <input type="checkbox" id="impacta_caja" name="impacta_caja" value="1" class="mt-1 {{ $checkbox }}"
                            aria-describedby="impacta-ayuda" @checked(old('impacta_caja', $medio?->impacta_caja ?? false))>
                        <div>
                            <label for="impacta_caja" class="text-gray-900">Impacta en la caja física</label>
                            <p id="impacta-ayuda" class="text-sm text-gray-600">Se cuenta en el arqueo al cerrar la caja (por ejemplo, el efectivo).</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="hidden" name="requiere_referencia" value="0">
                        <input type="checkbox" id="requiere_referencia" name="requiere_referencia" value="1" class="{{ $checkbox }}"
                            @checked(old('requiere_referencia', $medio?->requiere_referencia ?? false))>
                        <label for="requiere_referencia" class="text-gray-900">Requiere referencia (cupón, número de operación)</label>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="hidden" name="activo" value="0">
                        <input type="checkbox" id="activo" name="activo" value="1" class="{{ $checkbox }}"
                            @checked(old('activo', $medio?->activo ?? true))>
                        <label for="activo" class="text-gray-900">Activo</label>
                    </div>
                    <x-input-error :messages="array_merge($errors->get('impacta_caja'), $errors->get('requiere_referencia'), $errors->get('activo'))" />
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <a href="{{ route('caja.medios-pago.index') }}" class="text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Cancelar</a>
                </div>
            </form>

            @if ($medio?->tipo?->admitePlanes())
                <section aria-labelledby="planes-titulo" class="p-4 sm:p-8 bg-white shadow-sm sm:rounded-lg space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 id="planes-titulo" class="font-semibold text-gray-900">Planes de cuotas</h3>
                        <a href="{{ route('caja.medios-pago.planes.create', $medio) }}" class="text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Nuevo plan</a>
                    </div>

                    <ul class="divide-y divide-gray-100" aria-label="Planes de cuotas">
                        @forelse ($planes as $plan)
                            <li class="flex items-center justify-between gap-3 py-3">
                                <span class="{{ $plan->activo ? 'text-gray-900' : 'text-gray-500 line-through' }}">
                                    {{ $plan->cuotas }} {{ $plan->cuotas === 1 ? 'cuota' : 'cuotas' }}
                                    <span class="text-sm text-gray-600">· recargo {{ App\Services\Formato::porcentaje($plan->recargo_pct) }}</span>
                                    @unless ($plan->activo)
                                        <span class="sr-only">(inactivo)</span>
                                    @endunless
                                </span>
                                <a href="{{ route('caja.medios-pago.planes.edit', [$medio, $plan]) }}" class="text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                    Editar<span class="sr-only"> plan de {{ $plan->cuotas }} cuotas</span>
                                </a>
                            </li>
                        @empty
                            <li class="py-3 text-sm text-gray-600">Sin planes de cuotas.</li>
                        @endforelse
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
