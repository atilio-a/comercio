{{-- Un movimiento de caja. Variables: $movimiento, $conUsuario (bool), $puedeContraasentar (bool). --}}
@php($negativo = str_starts_with((string) $movimiento->importe, '-'))
<li class="p-4 bg-white shadow-sm sm:rounded-lg space-y-2">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="font-medium text-gray-900">
                {{ $movimiento->tipo->etiqueta() }}
                <span class="text-sm font-normal text-gray-600">· {{ $movimiento->medioPago->nombre }}</span>
                @if ($movimiento->esContraasiento())
                    <span class="ms-1 rounded-sm bg-amber-100 px-1.5 py-0.5 text-xs text-amber-900">Contraasiento del #{{ $movimiento->reversa_de_id }}</span>
                @elseif ($movimiento->contraasiento)
                    <span class="ms-1 rounded-sm bg-gray-200 px-1.5 py-0.5 text-xs text-gray-800">Revertido</span>
                @endif
            </p>
            @if ($movimiento->motivo)
                <p class="text-sm text-gray-700">{{ $movimiento->motivo }}</p>
            @endif
            <p class="text-xs text-gray-600">
                #{{ $movimiento->id }} · {{ $movimiento->created_at->format('d/m/Y H:i') }}
                @if ($movimiento->referencia)
                    · Ref. {{ $movimiento->referencia }}
                @endif
                @if ($conUsuario ?? false)
                    · {{ $movimiento->user?->name ?? 'Sistema' }}
                @endif
            </p>
        </div>
        <p class="text-lg font-semibold {{ $negativo ? 'text-red-700' : 'text-green-700' }}">
            {{ $negativo ? '−' : '+' }} $ {{ App\Services\Formato::importe(ltrim((string) $movimiento->importe, '-')) }}
        </p>
    </div>

    @if (($puedeContraasentar ?? false) && $movimiento->tipo->esManual() && ! $movimiento->esContraasiento() && ! $movimiento->contraasiento)
        @php($conError = (int) old('movimiento_id') === $movimiento->id)
        <details @if ($conError) open @endif>
            <summary class="cursor-pointer text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                Contraasentar<span class="sr-only"> movimiento #{{ $movimiento->id }}</span>
            </summary>
            <form method="POST" action="{{ route('caja.movimientos.contraasiento', $movimiento) }}" class="mt-2 flex flex-wrap items-end gap-3">
                @csrf
                <input type="hidden" name="movimiento_id" value="{{ $movimiento->id }}">
                <div class="grow">
                    <x-input-label :for="'motivo-contraasiento-'.$movimiento->id" value="Motivo del contraasiento" />
                    <x-text-input :id="'motivo-contraasiento-'.$movimiento->id" name="motivo" type="text" maxlength="255" class="mt-1 block w-full"
                        :value="$conError ? old('motivo') : ''" required />
                </div>
                <x-danger-button>Registrar contraasiento</x-danger-button>
            </form>
        </details>
    @endif
</li>
