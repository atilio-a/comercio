<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $cliente->nombre }}
                @unless ($cliente->activo)
                    <span class="ms-1 rounded-sm bg-red-100 px-1.5 py-0.5 text-xs text-red-800">Inactivo</span>
                @endunless
            </h2>

            @if ($puedeGestionar)
                <a href="{{ route('clientes.edit', $cliente) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Editar
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            <section aria-labelledby="datos-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg">
                <h3 id="datos-titulo" class="font-medium text-gray-900">Datos</h3>
                <dl class="mt-2 grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-gray-600">Documento</dt>
                        <dd class="text-gray-900">{{ $cliente->dni_cuit ? $cliente->documentoFormateado() : 'Sin documento' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-600">Condición frente al IVA</dt>
                        <dd class="text-gray-900">{{ $cliente->condicion_iva->etiqueta() }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-600">Teléfono</dt>
                        <dd class="text-gray-900">
                            @if ($cliente->telefono)
                                <a href="tel:{{ preg_replace('/[^\d+]/', '', $cliente->telefono) }}" class="underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">{{ $cliente->telefono }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-600">Email</dt>
                        <dd class="text-gray-900 break-all">{{ $cliente->email ?? '—' }}</dd>
                    </div>
                    @if ($usarTalles)
                        <div>
                            <dt class="text-gray-600">Talle habitual</dt>
                            <dd class="text-gray-900">{{ $cliente->talle_habitual ?? '—' }}</dd>
                        </div>
                    @endif
                    @if ($cliente->observaciones)
                        <div class="sm:col-span-2">
                            <dt class="text-gray-600">Observaciones</dt>
                            <dd class="text-gray-900 whitespace-pre-line">{{ $cliente->observaciones }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            @if ($puedeVerCuenta)
                <section aria-labelledby="cuenta-titulo" class="space-y-2">
                    <div class="p-4 bg-white shadow-sm sm:rounded-lg">
                        <h3 id="cuenta-titulo" class="font-medium text-gray-900">Cuenta corriente</h3>
                        @if ($cuenta)
                            <p class="mt-1 text-gray-900">
                                @if ($cuenta->saldoAFavor() !== '0.00')
                                    Saldo a favor: <strong class="text-green-700">$ {{ App\Services\Formato::importe($cuenta->saldoAFavor()) }}</strong>
                                @elseif ($cuenta->deuda() !== '0.00')
                                    Deuda: <strong class="text-red-700">$ {{ App\Services\Formato::importe($cuenta->deuda()) }}</strong>
                                @else
                                    Saldo: <strong>$ 0,00</strong>
                                @endif
                            </p>
                            <p class="text-sm text-gray-600">
                                {{ $cuenta->admite_credito ? 'Crédito habilitado hasta $ '.App\Services\Formato::importe($cuenta->limite_credito) : 'Sin crédito habilitado: solo saldo a favor.' }}
                                @if ($cuenta->bloqueada)
                                    · <span class="text-red-700">Bloqueada</span>
                                @endif
                            </p>
                        @else
                            <p class="mt-1 text-sm text-gray-600">El cliente todavía no tiene movimientos en cuenta corriente.</p>
                        @endif
                    </div>

                    @if ($cuenta)
                        <ol class="space-y-2" aria-label="Movimientos de cuenta corriente, del más reciente al más antiguo">
                            @forelse ($movimientos as $movimiento)
                                <li class="p-4 bg-white shadow-sm sm:rounded-lg flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900">
                                            {{ $movimiento->tipo->etiqueta() }}
                                            <span class="{{ $movimiento->tipo === App\Enums\TipoMovimientoCuenta::Debe ? 'text-red-700' : 'text-green-700' }}">$ {{ App\Services\Formato::importe($movimiento->importe) }}</span>
                                            @if ($movimiento->esContraasiento())
                                                <span class="ms-1 rounded-sm bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">Contraasiento</span>
                                            @endif
                                        </p>
                                        <p class="text-sm text-gray-700">{{ $movimiento->concepto }}</p>
                                        <p class="text-xs text-gray-600">
                                            Fecha {{ $movimiento->fecha->format('d/m/Y') }}
                                            @if ($movimiento->vencimiento)
                                                · vence {{ $movimiento->vencimiento->format('d/m/Y') }}
                                            @endif
                                            · {{ $movimiento->sucursal?->nombre }}
                                            · {{ $movimiento->user?->name ?? 'Sistema' }}
                                        </p>
                                    </div>
                                    <p class="text-end">
                                        <span class="block text-xs text-gray-600">Saldo</span>
                                        <span class="block font-semibold {{ App\Services\Importes::aCentavos((string) $movimiento->saldo) > 0 ? 'text-red-700' : 'text-gray-900' }}">$ {{ App\Services\Formato::importe($movimiento->saldo) }}</span>
                                    </p>
                                </li>
                            @empty
                                <li class="p-4 bg-white shadow-sm sm:rounded-lg text-gray-600">Sin movimientos.</li>
                            @endforelse
                        </ol>

                        {{ $movimientos->links() }}
                    @endif

                    <p class="text-xs text-gray-600">Saldo positivo: el cliente debe. Saldo negativo: el cliente tiene saldo a favor.</p>
                </section>
            @endif

            @include('clientes.partials.historial-ventas', ['cliente' => $cliente])
        </div>
    </div>
</x-app-layout>
