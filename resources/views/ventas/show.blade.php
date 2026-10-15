@php
    use App\Services\Formato;
    use App\Services\Importes;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Venta N° {{ $venta->id }}
                @if ($venta->estaAnulada())
                    <span class="ms-1 rounded-sm bg-red-100 px-1.5 py-0.5 text-xs text-red-800">Anulada</span>
                @endif
            </h2>

            <div class="flex flex-wrap items-center gap-2">
                @if ($puedeDevolver)
                    <a href="{{ route('ventas.devoluciones.create', $venta) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Devolver / cambiar
                    </a>
                @endif

                @can('ventas.vender')
                    <a href="{{ route('ventas.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Nueva venta
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            @if ($errors->anulacion->any())
                <div role="alert" class="p-4 bg-red-50 sm:rounded-lg">
                    <x-input-error :messages="$errors->anulacion->all()" />
                </div>
            @endif

            <section aria-labelledby="comprobantes-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg space-y-2">
                <h3 id="comprobantes-titulo" class="font-medium text-gray-900">Comprobantes</h3>
                @foreach (array_filter([$venta->comprobante, $venta->comprobanteAnulacion]) as $comprobante)
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-gray-900">{{ $comprobante->tipo->etiqueta() }} {{ $comprobante->numeroCompleto() }}</p>
                        <p class="flex gap-3 text-sm">
                            <a href="{{ route('comprobantes.show', [$comprobante, 'formato' => 'ticket']) }}" target="_blank" rel="noopener" class="text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                Ticket<span class="sr-only"> del comprobante {{ $comprobante->numeroCompleto() }} (se abre en otra pestaña)</span>
                            </a>
                            <a href="{{ route('comprobantes.show', [$comprobante, 'formato' => 'a4']) }}" target="_blank" rel="noopener" class="text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                A4 / PDF<span class="sr-only"> del comprobante {{ $comprobante->numeroCompleto() }} (se abre en otra pestaña)</span>
                            </a>
                        </p>
                    </div>
                @endforeach
                <p class="text-xs text-gray-600">{{ App\Models\Comprobante::LEYENDA_INTERNO }}. Para obtener el PDF, usá "Imprimir → Guardar como PDF".</p>
            </section>

            <section aria-labelledby="datos-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg">
                <h3 id="datos-titulo" class="font-medium text-gray-900">Datos</h3>
                <dl class="mt-2 grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-gray-600">Fecha</dt>
                        <dd class="text-gray-900">{{ $venta->fecha->format('d/m/Y') }} {{ $venta->created_at->format('H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-600">Cliente</dt>
                        <dd class="text-gray-900">
                            @if ($venta->cliente)
                                @can('clientes.ver')
                                    <a href="{{ route('clientes.show', $venta->cliente) }}" class="underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">{{ $venta->cliente->nombre }}</a>
                                @else
                                    {{ $venta->cliente->nombre }}
                                @endcan
                            @else
                                Consumidor final
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-600">Vendedor</dt>
                        <dd class="text-gray-900">{{ $venta->vendedor->user->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-600">Registró</dt>
                        <dd class="text-gray-900">{{ $venta->user?->name ?? 'Sistema' }} · punto de venta {{ $venta->puntoVenta->numeroFormateado() }}</dd>
                    </div>
                    @if ($venta->autorizanteDescuento)
                        <div>
                            <dt class="text-gray-600">Descuento general autorizado por</dt>
                            <dd class="text-gray-900">{{ $venta->autorizanteDescuento->name }}</dd>
                        </div>
                    @endif
                    @if ($venta->estaAnulada())
                        <div class="sm:col-span-2">
                            <dt class="text-gray-600">Anulación</dt>
                            <dd class="text-red-800">Anulada el {{ $venta->anulada_at->format('d/m/Y H:i') }} por {{ $venta->anuladaPor?->name ?? 'Sistema' }}: {{ $venta->motivo_anulacion }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section aria-labelledby="items-titulo" class="space-y-2">
                <h3 id="items-titulo" class="px-4 sm:px-0 font-medium text-gray-900">Productos</h3>
                <ul class="space-y-2">
                    @foreach ($venta->items as $item)
                        <li class="p-4 bg-white shadow-sm sm:rounded-lg flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900">{{ $item->variante->producto->nombre }} <span class="font-normal text-gray-700">{{ $item->variante->descripcion() }}</span></p>
                                <p class="text-sm text-gray-600">{{ $item->cantidad }} × $ {{ Formato::importe($item->precio_final) }} · SKU {{ $item->variante->sku }} · IVA {{ Formato::porcentaje($item->alicuota) }}</p>
                                @if (($cantidadesDevueltas[$item->id] ?? 0) > 0)
                                    <p class="text-sm font-medium text-amber-700">Devueltas: {{ $cantidadesDevueltas[$item->id] }}</p>
                                @endif
                                @if (Importes::aCentavos((string) $item->descuento) > 0)
                                    <p class="text-sm text-gray-600">Descuento $ {{ Formato::importe($item->descuento) }} (autorizó {{ $item->autorizanteDescuento?->name ?? '—' }})</p>
                                @endif
                                @if (Importes::aCentavos((string) $item->descuento_venta) > 0 || Importes::aCentavos((string) $item->recargo) > 0)
                                    <p class="text-xs text-gray-600">Descuento general $ {{ Formato::importe($item->descuento_venta) }} · recargo $ {{ Formato::importe($item->recargo) }}</p>
                                @endif
                            </div>
                            <p class="text-end">
                                <span class="block font-semibold text-gray-900">$ {{ Formato::importe($item->total) }}</span>
                                <span class="block text-xs text-gray-600">Neto $ {{ Formato::importe($item->neto) }} · IVA $ {{ Formato::importe($item->iva) }}</span>
                            </p>
                        </li>
                    @endforeach
                </ul>
            </section>

            @if ($venta->devoluciones->isNotEmpty())
                <section aria-labelledby="devoluciones-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg space-y-2">
                    <h3 id="devoluciones-titulo" class="font-medium text-gray-900">Devoluciones</h3>
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($venta->devoluciones as $devolucion)
                            <li class="py-2 flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <a href="{{ route('ventas.devoluciones.show', $devolucion) }}" class="font-medium text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                        Devolución N° {{ $devolucion->id }}
                                    </a>
                                    <span class="text-gray-600">· {{ $devolucion->fecha->format('d/m/Y') }} · {{ $devolucion->resolucion->etiqueta() }}</span>
                                    <p class="text-xs text-gray-600">Motivo: {{ $devolucion->motivo }}</p>
                                </div>
                                <div class="text-end">
                                    <span class="font-semibold text-gray-900">$ {{ Formato::importe($devolucion->importe) }}</span>
                                    @if ($devolucion->notaCredito)
                                        <p class="text-xs text-gray-600">NC {{ $devolucion->notaCredito->numeroCompleto() }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section aria-labelledby="pagos-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg">
                <h3 id="pagos-titulo" class="font-medium text-gray-900">Pagos</h3>
                <ul class="mt-2 divide-y divide-gray-100 text-sm">
                    @foreach ($venta->pagos as $pago)
                        <li class="py-2 flex flex-wrap justify-between gap-2">
                            <span class="text-gray-900">
                                {{ $pago->medioPago->nombre }}
                                @if ($pago->cuotas)
                                    en {{ $pago->cuotas }} {{ $pago->cuotas === 1 ? 'cuota' : 'cuotas' }}
                                @endif
                                @if ($pago->referencia || $pago->cupon)
                                    <span class="text-gray-600">· Ref. {{ $pago->referencia ?? $pago->cupon }}</span>
                                @endif
                            </span>
                            <span class="text-gray-900">
                                $ {{ Formato::importe($pago->cobrado()) }}
                                @if (Importes::aCentavos((string) $pago->recargo) > 0)
                                    <span class="text-gray-600">(incluye recargo de $ {{ Formato::importe($pago->recargo) }}, {{ Formato::porcentaje($pago->recargo_pct) }})</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>

                <dl class="mt-3 border-t border-gray-100 pt-3 grid grid-cols-2 gap-y-1 text-sm">
                    @if (Importes::aCentavos((string) $venta->descuento) > 0)
                        <dt class="text-gray-600">Descuentos</dt>
                        <dd class="text-end text-gray-900">− $ {{ Formato::importe($venta->descuento) }}</dd>
                    @endif
                    @if (Importes::aCentavos($descuentoGeneral) > 0)
                        <dt class="text-gray-600 ps-3">de ellos, general</dt>
                        <dd class="text-end text-gray-700">$ {{ Formato::importe($descuentoGeneral) }}</dd>
                    @endif
                    @if (Importes::aCentavos((string) $venta->recargo) > 0)
                        <dt class="text-gray-600">Recargo por cuotas</dt>
                        <dd class="text-end text-gray-900">$ {{ Formato::importe($venta->recargo) }}</dd>
                    @endif
                    <dt class="text-gray-600">Neto gravado</dt>
                    <dd class="text-end text-gray-900">$ {{ Formato::importe($venta->subtotal_neto) }}</dd>
                    <dt class="text-gray-600">IVA</dt>
                    <dd class="text-end text-gray-900">$ {{ Formato::importe($venta->iva) }}</dd>
                    <dt class="font-semibold text-gray-900">Total</dt>
                    <dd class="text-end font-semibold text-gray-900">$ {{ Formato::importe($venta->total) }}</dd>
                </dl>
            </section>

            @if ($puedeAnular)
                <section aria-labelledby="anular-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg">
                    <details @if ($errors->anulacion->any()) open @endif>
                        <summary id="anular-titulo" class="cursor-pointer text-sm text-red-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Anular venta</summary>
                        <p class="mt-2 text-sm text-gray-700">
                            Reingresa el stock, revierte los cobros en caja y en cuenta corriente y emite un comprobante de anulación.
                            La caja de la venta tiene que seguir abierta; si ya se cerró, registrá un cambio o devolución.
                        </p>
                        <form method="POST" action="{{ route('ventas.anular', $venta) }}" class="mt-2 flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="grow">
                                <x-input-label for="motivo" value="Motivo de la anulación" />
                                <x-text-input id="motivo" name="motivo" type="text" maxlength="200" class="mt-1 block w-full" :value="old('motivo')" required />
                            </div>
                            <x-danger-button>Anular venta</x-danger-button>
                        </form>
                    </details>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
