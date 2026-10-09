<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Mi caja
                @if ($sesion)
                    <span class="text-base font-normal text-gray-600">· {{ $sesion->caja->nombre }}</span>
                @endif
            </h2>
            @if ($sesion)
                <a href="{{ route('caja.cierre.edit') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Cerrar caja
                </a>
            @endif
        </div>
    </x-slot>

    @php($select = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs')

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            @if (! $sesion)
                @php($libres = $cajas->filter(fn ($caja) => ! $caja->sesionAbierta))
                <form method="POST" action="{{ route('caja.abrir') }}" class="p-4 sm:p-8 bg-white shadow-sm sm:rounded-lg space-y-6">
                    @csrf
                    <h3 class="font-semibold text-gray-900">Abrir caja</h3>

                    @if ($cajas->isEmpty())
                        <p class="text-sm text-gray-700">La sucursal no tiene cajas activas. Pedile al Administrador que cree una.</p>
                    @else
                        <div>
                            <x-input-label for="caja_id" value="Caja" />
                            <select id="caja_id" name="caja_id" class="{{ $select }}" required>
                                @foreach ($cajas as $caja)
                                    <option value="{{ $caja->id }}" @selected((int) old('caja_id', $libres->first()?->id) === $caja->id) @disabled($caja->sesionAbierta)>
                                        {{ $caja->nombre }}{{ $caja->sesionAbierta ? ' (abierta por '.$caja->sesionAbierta->user->name.')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('caja_id')" />
                        </div>

                        <div>
                            <x-input-label for="fondo_inicial" value="Fondo inicial en efectivo" />
                            <x-text-input id="fondo_inicial" name="fondo_inicial" type="number" min="0" step="0.01" inputmode="decimal"
                                class="mt-1 block w-full" :value="old('fondo_inicial', '0')" required aria-describedby="fondo-ayuda" />
                            <p id="fondo-ayuda" class="mt-1 text-sm text-gray-600">El cambio con el que arranca la caja.</p>
                            <x-input-error class="mt-2" :messages="$errors->get('fondo_inicial')" />
                        </div>

                        <x-primary-button :disabled="$libres->isEmpty()">Abrir caja</x-primary-button>
                    @endif
                </form>
            @else
                <p class="text-sm text-gray-600">
                    Abierta el {{ $sesion->apertura->format('d/m/Y H:i') }} con un fondo de $ {{ App\Services\Formato::importe($sesion->fondo_inicial) }}.
                </p>

                @include('caja.partials.esperado', ['esperado' => $esperado])

                <form method="POST" action="{{ route('caja.movimientos.store') }}" class="p-4 sm:p-6 bg-white shadow-sm sm:rounded-lg space-y-4">
                    @csrf
                    <fieldset>
                        <legend class="font-semibold text-gray-900">Registrar ingreso o egreso</legend>
                        <div class="mt-2 flex flex-wrap gap-4">
                            @foreach ([App\Enums\TipoMovimientoCaja::Egreso, App\Enums\TipoMovimientoCaja::Ingreso] as $tipo)
                                <label class="inline-flex items-center gap-2">
                                    <input type="radio" name="tipo" value="{{ $tipo->value }}" required
                                        class="border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500"
                                        @checked(old('tipo', App\Enums\TipoMovimientoCaja::Egreso->value) === $tipo->value)>
                                    <span class="text-gray-900">{{ $tipo->etiqueta() }}</span>
                                </label>
                            @endforeach
                        </div>
                        <x-input-error class="mt-1" :messages="$errors->get('tipo')" />
                    </fieldset>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="importe" value="Importe" />
                            <x-text-input id="importe" name="importe" type="number" min="0.01" step="0.01" inputmode="decimal"
                                class="mt-1 block w-full" :value="old('importe')" required />
                            <x-input-error class="mt-1" :messages="$errors->get('importe')" />
                        </div>
                        <div>
                            <x-input-label for="medio_pago_id" value="Medio de pago" />
                            <select id="medio_pago_id" name="medio_pago_id" class="{{ $select }}" required>
                                @foreach ($medios as $medio)
                                    <option value="{{ $medio->id }}" @selected((int) old('medio_pago_id', $medios->firstWhere('impacta_caja', true)?->id) === $medio->id)>
                                        {{ $medio->nombre }}{{ $medio->requiere_referencia ? ' (pide referencia)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-1" :messages="$errors->get('medio_pago_id')" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="motivo" value="Motivo" />
                            <x-text-input id="motivo" name="motivo" type="text" maxlength="255" class="mt-1 block w-full"
                                :value="old('motivo')" required placeholder="Retiro, gasto, pago a proveedor…" />
                            <x-input-error class="mt-1" :messages="$errors->get('motivo')" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="referencia" value="Referencia (opcional)" />
                            <x-text-input id="referencia" name="referencia" type="text" maxlength="100" class="mt-1 block w-full"
                                :value="old('referencia')" aria-describedby="referencia-ayuda" />
                            <p id="referencia-ayuda" class="mt-1 text-sm text-gray-600">Número de operación o comprobante. Obligatoria si el medio la pide.</p>
                            <x-input-error class="mt-1" :messages="$errors->get('referencia')" />
                        </div>
                    </div>

                    <x-primary-button>Registrar</x-primary-button>
                </form>

                <section aria-labelledby="movimientos-titulo" class="space-y-2">
                    <h3 id="movimientos-titulo" class="font-semibold text-gray-900 px-4 sm:px-0">Movimientos</h3>
                    <ol class="space-y-2" aria-label="Movimientos, del más reciente al más antiguo">
                        @forelse ($movimientos as $movimiento)
                            @include('caja.partials.movimiento', ['movimiento' => $movimiento, 'conUsuario' => false, 'puedeContraasentar' => false])
                        @empty
                            <li class="p-4 bg-white shadow-sm sm:rounded-lg text-sm text-gray-600">Todavía no hay movimientos.</li>
                        @endforelse
                    </ol>
                    {{ $movimientos->links() }}
                </section>
            @endif

            @if ($anteriores->isNotEmpty())
                <section aria-labelledby="anteriores-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg">
                    <h3 id="anteriores-titulo" class="font-semibold text-gray-900">Mis últimos cierres</h3>
                    <ul class="mt-2 divide-y divide-gray-100">
                        @foreach ($anteriores as $anterior)
                            <li class="py-2">
                                <a href="{{ route('caja.sesiones.show', $anterior) }}" class="text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                    {{ $anterior->caja->nombre }} · {{ $anterior->fecha->format('d/m/Y') }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
