<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Sesiones de caja</h2>
    </x-slot>

    @php($select = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs')

    <div class="py-6 sm:py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('caja.sesiones.index') }}" class="p-4 bg-white shadow-sm sm:rounded-lg grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-end">
                <div>
                    <x-input-label for="fecha" value="Fecha" />
                    <x-text-input id="fecha" name="fecha" type="date" class="mt-1 block w-full" :value="$filtros['fecha'] ?? ''" />
                    <x-input-error class="mt-1" :messages="$errors->get('fecha')" />
                </div>
                <div>
                    <x-input-label for="estado" value="Estado" />
                    <select id="estado" name="estado" class="{{ $select }}">
                        <option value="">Todas</option>
                        @foreach (App\Enums\EstadoSesionCaja::cases() as $estado)
                            <option value="{{ $estado->value }}" @selected(($filtros['estado'] ?? null) === $estado->value)>{{ $estado->etiqueta() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <x-primary-button>Filtrar</x-primary-button>
                    <a href="{{ route('caja.sesiones.index') }}" class="inline-flex items-center px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Limpiar</a>
                </div>
            </form>

            <ul class="space-y-2" aria-label="Sesiones de caja">
                @forelse ($sesiones as $sesion)
                    @php($diferencia = $sesion->diferencia_total !== null ? App\Services\Importes::normalizar((string) $sesion->diferencia_total) : null)
                    <li class="p-4 bg-white shadow-sm sm:rounded-lg flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900">
                                {{ $sesion->caja->nombre }} · {{ $sesion->user->name }}
                                <span class="ms-1 rounded-sm px-1.5 py-0.5 text-xs {{ $sesion->estaAbierta() ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-800' }}">{{ $sesion->estado->etiqueta() }}</span>
                            </p>
                            <p class="text-sm text-gray-600">
                                Apertura {{ $sesion->apertura->format('d/m/Y H:i') }}
                                @if ($sesion->cierre)
                                    · cierre {{ $sesion->cierre->format('d/m/Y H:i') }}
                                @endif
                                @if ($diferencia !== null && $diferencia !== '0.00')
                                    · <span class="{{ str_starts_with($diferencia, '-') ? 'text-red-700' : 'text-amber-700' }}">diferencia $ {{ App\Services\Formato::importe($diferencia) }}</span>
                                @endif
                            </p>
                        </div>
                        <a href="{{ route('caja.sesiones.show', $sesion) }}" class="shrink-0 text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                            Ver<span class="sr-only"> sesión de {{ $sesion->caja->nombre }} del {{ $sesion->apertura->format('d/m/Y H:i') }}</span>
                        </a>
                    </li>
                @empty
                    <li class="p-4 bg-white shadow-sm sm:rounded-lg text-sm text-gray-600">No hay sesiones para ese filtro.</li>
                @endforelse
            </ul>

            {{ $sesiones->links() }}
        </div>
    </div>
</x-app-layout>
