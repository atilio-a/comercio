@php
    use App\Enums\ResolucionDevolucion;
    use App\Services\Formato;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Devolución N° {{ $devolucion->id }}
            </h2>

            <div class="flex flex-wrap items-center gap-2">
                @if ($devolucion->resolucion === ResolucionDevolucion::SaldoAFavor && $devolucion->cliente_id && $puedeVender)
                    <a href="{{ route('ventas.create', ['cliente_id' => $devolucion->cliente_id]) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Usar el saldo en una nueva venta
                    </a>
                @endif
                <a href="{{ route('ventas.show', $devolucion->venta_id) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Volver a la venta
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            @if ($devolucion->notaCredito)
                <section aria-labelledby="comprobante-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg space-y-2">
                    <h3 id="comprobante-titulo" class="font-medium text-gray-900">Comprobante</h3>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-gray-900 font-medium">
                            {{ $devolucion->notaCredito->tipo->etiqueta() }} {{ $devolucion->notaCredito->numeroCompleto() }}
                        </p>
                        <p class="flex gap-3 text-sm">
                            <a href="{{ route('comprobantes.show', [$devolucion->notaCredito, 'formato' => 'ticket']) }}" target="_blank" rel="noopener" class="text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                Ticket<span class="sr-only"> de la nota de crédito (se abre en otra pestaña)</span>
                            </a>
                            <a href="{{ route('comprobantes.show', [$devolucion->notaCredito, 'formato' => 'a4']) }}" target="_blank" rel="noopener" class="text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                A4 / PDF<span class="sr-only"> de la nota de crédito (se abre en otra pestaña)</span>
                            </a>
                        </p>
                    </div>
                    <p class="text-xs text-gray-600">{{ App\Models\Comprobante::LEYENDA_INTERNO }}. Para obtener el PDF, usá "Imprimir → Guardar como PDF".</p>
                </section>
            @endif

            <section aria-labelledby="datos-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg">
                <h3 id="datos-titulo" class="font-medium text-gray-900">Datos de la devolución</h3>
                <dl class="mt-2 grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-gray-600">Fecha y hora</dt>
                        <dd class="text-gray-900">{{ $devolucion->fecha->format('d/m/Y') }} {{ $devolucion->created_at->format('H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-600">Venta original</dt>
                        <dd class="text-gray-900">
                            <a href="{{ route('ventas.show', $devolucion->venta_id) }}" class="underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                Venta N° {{ $devolucion->venta_id }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-600">Resolución</dt>
                        <dd class="text-gray-900 font-medium">{{ $devolucion->resolucion->etiqueta() }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-600">Cliente</dt>
                        <dd class="text-gray-900">
                            @if ($devolucion->cliente)
                                @can('clientes.ver')
                                    <a href="{{ route('clientes.show', $devolucion->cliente) }}" class="underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                        {{ $devolucion->cliente->nombre }}
                                    </a>
                                @else
                                    {{ $devolucion->cliente->nombre }}
                                @endcan
                                @if ($devolucion->cliente->cuentaCorriente && $devolucion->resolucion === ResolucionDevolucion::SaldoAFavor)
                                    <span class="block text-xs text-indigo-700">Saldo a favor actual: $ {{ Formato::importe($devolucion->cliente->cuentaCorriente->saldoAFavor()) }}</span>
                                @endif
                            @else
                                Consumidor final
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-600">Registró</dt>
                        <dd class="text-gray-900">{{ $devolucion->user?->name ?? 'Sistema' }}</dd>
                    </div>
                    @if ($devolucion->medioPago)
                        <div>
                            <dt class="text-gray-600">Medio del reembolso</dt>
                            <dd class="text-gray-900">{{ $devolucion->medioPago->nombre }} (sesión N° {{ $devolucion->caja_sesion_id }})</dd>
                        </div>
                    @endif
                    <div class="sm:col-span-2">
                        <dt class="text-gray-600">Motivo</dt>
                        <dd class="text-gray-900">{{ $devolucion->motivo }}</dd>
                    </div>
                </dl>
            </section>

            <section aria-labelledby="items-titulo" class="space-y-2">
                <h3 id="items-titulo" class="px-4 sm:px-0 font-medium text-gray-900">Productos devueltos</h3>
                <ul class="space-y-2">
                    @foreach ($devolucion->items as $item)
                        <li class="p-4 bg-white shadow-sm sm:rounded-lg flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900">
                                    {{ $item->variante->producto->nombre }}
                                    <span class="font-normal text-gray-700">{{ $item->variante->descripcion() }}</span>
                                </p>
                                <p class="text-sm text-gray-600">
                                    {{ $item->cantidad }} {{ $item->cantidad === 1 ? 'unidad devuelta' : 'unidades devueltas' }}
                                    · SKU {{ $item->variante->sku }}
                                </p>
                            </div>
                            <p class="text-end">
                                <span class="block font-semibold text-gray-900">$ {{ Formato::importe($item->importe) }}</span>
                                <span class="block text-xs text-gray-600">Neto $ {{ Formato::importe($item->neto) }} · IVA $ {{ Formato::importe($item->iva) }}</span>
                            </p>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section aria-labelledby="totales-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg">
                <h3 id="totales-titulo" class="sr-only">Totales</h3>
                <dl class="grid grid-cols-2 gap-y-1 text-sm">
                    <dt class="text-gray-600">Neto gravado</dt>
                    <dd class="text-end text-gray-900">$ {{ Formato::importe($devolucion->neto) }}</dd>
                    <dt class="text-gray-600">IVA</dt>
                    <dd class="text-end text-gray-900">$ {{ Formato::importe($devolucion->iva) }}</dd>
                    <dt class="font-semibold text-gray-900 text-base pt-2 border-t border-gray-100">Total devuelto</dt>
                    <dd class="text-end font-semibold text-gray-900 text-base pt-2 border-t border-gray-100">$ {{ Formato::importe($devolucion->importe) }}</dd>
                </dl>
            </section>
        </div>
    </div>
</x-app-layout>
