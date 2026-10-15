@php
    use App\Enums\ResolucionDevolucion;
    use App\Services\Formato;

    $select = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs';
    $botonSecundario = 'inline-flex items-center justify-center min-h-11 px-3 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-indigo-500';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Devolución / cambio · Venta N° {{ $venta->id }}
            </h2>
            <a href="{{ route('ventas.show', $venta) }}" class="text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                Volver a la venta
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12" x-data="devolucion({{ Js::from($configuracion) }})">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($errors->any())
                <div role="alert" class="p-4 bg-red-50 text-red-800 sm:rounded-lg space-y-1">
                    <p class="font-medium">No se pudo registrar la devolución:</p>
                    <ul class="list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('ventas.devoluciones.store', $venta) }}" class="space-y-4">
                @csrf

                {{-- Products selection --}}
                <section aria-labelledby="productos-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg space-y-3">
                    <h3 id="productos-titulo" class="font-medium text-gray-900">Productos a devolver</h3>
                    <p class="text-sm text-gray-600">Indicá la cantidad de unidades que se devuelven de cada ítem.</p>

                    <ul class="divide-y divide-gray-100" aria-label="Productos de la venta">
                        @foreach ($venta->items as $item)
                            @php
                                $devueltas = $cantidadesDevueltas[$item->id] ?? 0;
                                $disponibles = max(0, $item->cantidad - $devueltas);
                                $oldCantidad = old("cantidades.{$item->id}", 0);
                            @endphp
                            <li class="py-3 flex flex-wrap items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900">
                                        {{ $item->variante->producto->nombre }}
                                        <span class="font-normal text-gray-700">{{ $item->variante->descripcion() }}</span>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        Precio: $ {{ Formato::importe($item->precio_final) }}
                                        · Vendidas: {{ $item->cantidad }}
                                        @if ($devueltas > 0)
                                            · Ya devueltas: {{ $devueltas }}
                                        @endif
                                        · <span class="{{ $disponibles > 0 ? 'text-gray-900 font-medium' : 'text-gray-400' }}">Quedan: {{ $disponibles }}</span>
                                    </p>
                                </div>

                                <div class="w-32">
                                    <x-input-label :for="'cantidades_'.$item->id" value="Cantidad" class="sr-only" />
                                    @if ($disponibles > 0)
                                        <x-text-input
                                            :id="'cantidades_'.$item->id"
                                            :name="'cantidades['.$item->id.']'"
                                            type="number"
                                            min="0"
                                            :max="$disponibles"
                                            :value="$oldCantidad"
                                            class="block w-full text-end"
                                        />
                                    @else
                                        <span class="block py-2 text-end text-sm text-gray-400 italic">Completo</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <x-input-error :messages="$errors->get('cantidades')" />
                </section>

                {{-- Reason and resolution --}}
                <section aria-labelledby="resolucion-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg space-y-4">
                    <h3 id="resolucion-titulo" class="font-medium text-gray-900">Resolución</h3>

                    <div>
                        <x-input-label for="motivo" value="Motivo de la devolución" />
                        <x-text-input id="motivo" name="motivo" type="text" maxlength="200" class="mt-1 block w-full" :value="old('motivo')" required placeholder="Ej: Talle chico, cambio por otro modelo, falla de fábrica" />
                        <x-input-error class="mt-1" :messages="$errors->get('motivo')" />
                    </div>

                    <div>
                        <x-input-label for="resolucion" value="Cómo se resuelve" />
                        <select id="resolucion" name="resolucion" x-model="resolucion" class="{{ $select }}" required>
                            <option value="{{ ResolucionDevolucion::SaldoAFavor->value }}">Saldo a favor en cuenta corriente (nota de crédito / cambio)</option>
                            @if ($puedeReembolsar && $admiteReembolso)
                                <option value="{{ ResolucionDevolucion::Reembolso->value }}">Reembolso en dinero desde la caja</option>
                            @endif
                        </select>
                        <x-input-error class="mt-1" :messages="$errors->get('resolucion')" />

                        @if (! $puedeReembolsar)
                            <p class="mt-1 text-xs text-gray-500">El reembolso en dinero requiere el rol Gerente (permiso de reembolso).</p>
                        @elseif (! $admiteReembolso)
                            <p class="mt-1 text-xs text-amber-700">Esta venta se cobró con saldo a favor o cuenta corriente: la devolución solo se acredita en cuenta corriente.</p>
                        @elseif (! $cajaAbierta)
                            <p class="mt-1 text-xs text-amber-700">No tenés la caja abierta: para reembolsar primero abrila desde Caja.</p>
                        @endif
                    </div>

                    {{-- Saldo a favor details --}}
                    <div x-show="resolucion === '{{ ResolucionDevolucion::SaldoAFavor->value }}'" class="rounded-md border border-indigo-100 bg-indigo-50/50 p-4 space-y-3">
                        <p class="text-sm font-medium text-indigo-900">Acreditación en cuenta corriente</p>
                        <p class="text-xs text-indigo-700">
                            Se emitirá una nota de crédito interna y el importe quedará acreditado a favor del cliente. Si desea cambiarlo por otro producto, podrá usar el saldo de inmediato en una nueva venta.
                        </p>

                        @if ($venta->cliente)
                            <div class="flex items-center gap-2 text-sm text-gray-900">
                                <span>Cliente de la venta: <strong>{{ $venta->cliente->nombre }}</strong></span>
                                @if ($venta->cliente->dni_cuit)
                                    <span class="text-gray-500">({{ $venta->cliente->documentoFormateado() }})</span>
                                @endif
                                <input type="hidden" name="cliente_id" value="{{ $venta->cliente->id }}">
                            </div>
                        @else
                            <div class="space-y-3">
                                <p class="text-sm text-gray-800">
                                    La venta fue a consumidor final. Para acreditar saldo a favor, seleccioná o creá el cliente:
                                </p>

                                <input type="hidden" name="cliente_id" :value="cliente?.id ?? ''">

                                <div x-show="cliente" class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-white p-3 border border-indigo-200">
                                    <p class="text-gray-900 font-medium">
                                        <span x-text="cliente?.nombre"></span>
                                        <span class="text-sm text-gray-600" x-text="cliente?.documento"></span>
                                    </p>
                                    <button type="button" class="text-sm text-gray-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500" @click="quitarCliente()">Cambiar cliente</button>
                                </div>

                                <div x-show="!cliente" class="space-y-2">
                                    <div>
                                        <x-input-label for="cliente-busqueda" value="Buscar cliente" />
                                        <x-text-input id="cliente-busqueda" type="search" class="mt-1 block w-full bg-white" autocomplete="off" x-model="clienteBusqueda" @input="buscarClientes()" placeholder="Nombre, documento o teléfono" />
                                    </div>

                                    <ul class="divide-y divide-gray-100 bg-white rounded-md border border-gray-200 max-h-48 overflow-y-auto" x-show="clientes.length > 0" aria-label="Clientes encontrados">
                                        <template x-for="encontrado in clientes" :key="encontrado.id">
                                            <li>
                                                <button type="button" class="w-full min-h-11 px-3 py-2 text-start hover:bg-gray-50 focus:outline-hidden focus:bg-indigo-50" @click="elegirCliente(encontrado)">
                                                    <span class="font-medium text-gray-900" x-text="encontrado.nombre"></span>
                                                    <span class="block text-xs text-gray-600" x-text="encontrado.documento || encontrado.telefono"></span>
                                                </button>
                                            </li>
                                        </template>
                                    </ul>

                                    @if ($puedeCrearClientes)
                                        <details class="rounded-md border border-gray-200 bg-white p-3">
                                            <summary class="cursor-pointer text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Alta rápida de cliente</summary>
                                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                                                <div>
                                                    <x-input-label for="cliente-nombre" value="Nombre" />
                                                    <x-text-input id="cliente-nombre" type="text" maxlength="150" class="mt-1 block w-full" x-model="clienteNuevo.nombre" />
                                                    <p class="mt-1 text-xs text-red-600" x-text="(erroresCliente.nombre ?? [])[0] ?? ''"></p>
                                                </div>
                                                <div>
                                                    <x-input-label for="cliente-telefono" value="Teléfono" />
                                                    <x-text-input id="cliente-telefono" type="tel" maxlength="50" class="mt-1 block w-full" x-model="clienteNuevo.telefono" />
                                                    <p class="mt-1 text-xs text-red-600" x-text="(erroresCliente.telefono ?? [])[0] ?? ''"></p>
                                                </div>
                                                <div>
                                                    <x-input-label for="cliente-documento" value="DNI o CUIT" />
                                                    <x-text-input id="cliente-documento" type="text" inputmode="numeric" maxlength="20" class="mt-1 block w-full" x-model="clienteNuevo.dni_cuit" />
                                                    <p class="mt-1 text-xs text-red-600" x-text="(erroresCliente.dni_cuit ?? [])[0] ?? ''"></p>
                                                </div>
                                            </div>
                                            <button type="button" class="{{ $botonSecundario }} mt-2" @click="crearCliente()">Crear y elegir</button>
                                        </details>
                                    @endif
                                </div>
                                <x-input-error :messages="$errors->get('cliente_id')" />
                            </div>
                        @endif
                    </div>

                    {{-- Reembolso details --}}
                    @if ($puedeReembolsar && $admiteReembolso)
                        <div x-show="resolucion === '{{ ResolucionDevolucion::Reembolso->value }}'" class="rounded-md border border-amber-200 bg-amber-50/50 p-4 space-y-3">
                            <p class="text-sm font-medium text-amber-900">Reembolso desde la caja</p>
                            <p class="text-xs text-amber-800">
                                Se registrará un egreso en tu sesión de caja abierta y se emitirá la nota de crédito interna.
                            </p>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <x-input-label for="medio_pago_id" value="Medio de egreso" />
                                    <select id="medio_pago_id" name="medio_pago_id" class="{{ $select }}">
                                        <option value="">Elegí el medio de pago</option>
                                        @foreach ($mediosReembolso as $medio)
                                            <option value="{{ $medio->id }}" @selected(old('medio_pago_id') == $medio->id)>{{ $medio->nombre }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error class="mt-1" :messages="$errors->get('medio_pago_id')" />
                                </div>
                                <div>
                                    <x-input-label for="referencia" value="Referencia / comprobante (opcional)" />
                                    <x-text-input id="referencia" name="referencia" type="text" maxlength="100" class="mt-1 block w-full" :value="old('referencia')" />
                                    <x-input-error class="mt-1" :messages="$errors->get('referencia')" />
                                </div>
                            </div>
                        </div>
                    @endif
                </section>

                <div class="flex flex-wrap items-center justify-between gap-3 p-4 bg-white shadow-sm sm:rounded-lg">
                    <a href="{{ route('ventas.show', $venta) }}" class="text-sm text-gray-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                        Cancelar y volver
                    </a>
                    <x-primary-button>
                        Registrar devolución
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
