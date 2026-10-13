{{-- Latest sales of the customer in the active branch, with the same visibility as the sales list. --}}
<section aria-labelledby="ventas-titulo" class="space-y-2">
    <div class="p-4 bg-white shadow-sm sm:rounded-lg">
        <h3 id="ventas-titulo" class="font-medium text-gray-900">Historial de compras</h3>
        @if ($ventas->isEmpty())
            <p class="mt-1 text-sm text-gray-600">Sin compras registradas en esta sucursal.</p>
        @else
            <p class="mt-1 text-sm text-gray-600">Últimas {{ $ventas->count() }} compras en esta sucursal.</p>
        @endif
    </div>

    @if ($ventas->isNotEmpty())
        <ol class="space-y-2" aria-label="Compras del cliente, de la más reciente a la más antigua">
            @foreach ($ventas as $venta)
                <li class="p-4 bg-white shadow-sm sm:rounded-lg flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900">
                            <a href="{{ route('ventas.show', $venta) }}" class="underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Venta N° {{ $venta->id }}</a>
                            @if ($venta->comprobante)
                                <span class="text-sm font-normal text-gray-600">· Comp. {{ $venta->comprobante->numeroCompleto() }}</span>
                            @endif
                            @if ($venta->estaAnulada())
                                <span class="ms-1 rounded-sm bg-red-100 px-1.5 py-0.5 text-xs text-red-800">Anulada</span>
                            @endif
                        </p>
                        <p class="text-sm text-gray-600">{{ $venta->fecha->format('d/m/Y') }} · {{ $venta->vendedor->user->name }}</p>
                    </div>
                    <p class="font-semibold text-gray-900 {{ $venta->estaAnulada() ? 'line-through' : '' }}">$ {{ App\Services\Formato::importe($venta->total) }}</p>
                </li>
            @endforeach
        </ol>
    @endif
</section>
