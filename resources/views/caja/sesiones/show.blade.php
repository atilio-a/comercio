<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $sesion->caja->nombre }} · {{ $sesion->user->name }}
            <span class="ms-1 rounded-sm px-1.5 py-0.5 text-xs align-middle {{ $sesion->estaAbierta() ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-800' }}">{{ $sesion->estado->etiqueta() }}</span>
        </h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            @if ($errors->contraasiento->any())
                <div role="alert" class="p-4 bg-red-50 sm:rounded-lg">
                    <x-input-error :messages="$errors->contraasiento->all()" />
                </div>
            @endif

            <p class="p-4 bg-white shadow-sm sm:rounded-lg text-sm text-gray-700">
                Fecha {{ $sesion->fecha->format('d/m/Y') }} · apertura {{ $sesion->apertura->format('d/m/Y H:i') }}
                · fondo $ {{ App\Services\Formato::importe($sesion->fondo_inicial) }}
                @if ($sesion->cierre)
                    · cierre {{ $sesion->cierre->format('d/m/Y H:i') }}
                @endif
            </p>

            @if ($esperado !== null)
                @include('caja.partials.esperado', ['esperado' => $esperado])
            @else
                <section aria-labelledby="arqueo-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg space-y-2">
                    <h3 id="arqueo-titulo" class="font-semibold text-gray-900">Arqueo</h3>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($sesion->arqueos as $arqueo)
                            <li class="py-2 flex flex-wrap items-center justify-between gap-2">
                                <span class="text-gray-900">{{ $arqueo->medioPago->nombre }}</span>
                                <span class="text-sm text-gray-700">
                                    Esperado $ {{ App\Services\Formato::importe($arqueo->esperado) }}
                                    @if ($arqueo->contado !== null)
                                        · contado $ {{ App\Services\Formato::importe($arqueo->contado) }}
                                        · <span class="{{ $arqueo->diferencia === '0.00' ? 'text-gray-700' : (str_starts_with($arqueo->diferencia, '-') ? 'text-red-700 font-semibold' : 'text-amber-700 font-semibold') }}">diferencia $ {{ App\Services\Formato::importe($arqueo->diferencia) }}</span>
                                    @else
                                        · no se cuenta
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($sesion->justificacion)
                        <p class="text-sm text-gray-700"><strong>Justificación:</strong> {{ $sesion->justificacion }}</p>
                    @endif
                </section>
            @endif

            <section aria-labelledby="movimientos-titulo" class="space-y-2">
                <h3 id="movimientos-titulo" class="font-semibold text-gray-900 px-4 sm:px-0">Movimientos</h3>
                <ol class="space-y-2" aria-label="Movimientos, del más reciente al más antiguo">
                    @forelse ($movimientos as $movimiento)
                        @include('caja.partials.movimiento', ['movimiento' => $movimiento, 'conUsuario' => true, 'puedeContraasentar' => $puedeContraasentar])
                    @empty
                        <li class="p-4 bg-white shadow-sm sm:rounded-lg text-sm text-gray-600">Sin movimientos.</li>
                    @endforelse
                </ol>
                {{ $movimientos->links() }}
            </section>

            <div class="flex flex-wrap gap-4 px-4 sm:px-0">
                @can('caja.supervisar')
                    <a href="{{ route('caja.sesiones.index') }}" class="text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Volver a las sesiones</a>
                @endcan
                @can('caja.operar')
                    <a href="{{ route('caja.mi-caja') }}" class="text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Mi caja</a>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
