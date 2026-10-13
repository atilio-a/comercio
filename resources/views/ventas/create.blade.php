@php
    $select = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs';
    $botonSecundario = 'inline-flex items-center justify-center min-h-11 px-3 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-indigo-500';
    $configuracion['puntoVentaId'] = $puntosVenta->first()?->id;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Venta rápida</h2>
            <a href="{{ route('ventas.index') }}" class="text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Ventas</a>
        </div>
    </x-slot>

    <div class="py-4 sm:py-8" x-data="ventaRapida({{ Js::from($configuracion) }})">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <p class="sr-only" role="status" aria-live="polite" x-text="mensaje"></p>

            @unless ($cajaAbierta)
                <p role="alert" class="p-4 bg-amber-50 text-amber-900 sm:rounded-lg text-sm">
                    No tenés la caja abierta: solo podés cobrar con saldo a favor o cuenta corriente.
                    @can('caja.operar')
                        <a href="{{ route('caja.mi-caja') }}" class="underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Abrir la caja</a>.
                    @endcan
                </p>
            @endunless

            @if ($puntosVenta->isEmpty())
                <p role="alert" class="p-4 bg-red-50 text-red-800 sm:rounded-lg text-sm">La sucursal no tiene un punto de venta activo: pedile al Administrador que cree uno.</p>
            @endif

            @if ($vendedores->isEmpty())
                <p role="alert" class="p-4 bg-red-50 text-red-800 sm:rounded-lg text-sm">No hay vendedores disponibles en la sucursal: marcá a un usuario como vendedor desde Usuarios.</p>
            @endif

            {{-- Products --}}
            <section aria-labelledby="productos-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg space-y-3">
                <h3 id="productos-titulo" class="font-medium text-gray-900">Productos</h3>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <x-input-label for="busqueda" value="Buscar por nombre, SKU o código" />
                        <x-text-input id="busqueda" type="search" class="mt-1 block w-full" autocomplete="off" x-model="busqueda" @input="buscar()" />
                    </div>
                    <div>
                        <x-input-label for="codigo" value="Escanear código (lector)" />
                        <x-text-input id="codigo" type="text" inputmode="numeric" class="mt-1 block w-full" autocomplete="off" x-model="codigo" @keydown.enter.prevent="escanear()" aria-describedby="codigo-ayuda" />
                        <p id="codigo-ayuda" class="mt-1 text-xs text-gray-600">El lector escribe el código y Enter: agrega una unidad.</p>
                    </div>
                </div>

                <p class="text-sm text-gray-600" x-show="buscando">Buscando…</p>

                <ul class="divide-y divide-gray-100" x-show="resultados.length > 0 && !seleccion" aria-label="Productos encontrados">
                    <template x-for="producto in resultados" :key="producto.producto_id">
                        <li>
                            <button type="button" class="w-full min-h-11 py-2 text-start rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500" @click="elegirProducto(producto)">
                                <span class="font-medium text-gray-900" x-text="producto.nombre"></span>
                                <span class="block text-sm text-gray-600" x-text="`$ ${importe(producto.variantes[0]?.precio_final)} · ${producto.variantes.length} ${producto.variantes.length === 1 ? 'variante' : 'variantes'}`"></span>
                            </button>
                        </li>
                    </template>
                </ul>

                <template x-if="seleccion">
                    <div class="space-y-3 border-t border-gray-100 pt-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-medium text-gray-900" x-text="seleccion.producto.nombre"></p>
                            <button type="button" class="text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500" @click="seleccion = null">Cancelar</button>
                        </div>

                        <fieldset x-show="pideTalle()">
                            <legend class="text-sm text-gray-700">Talle</legend>
                            <div class="mt-1 flex flex-wrap gap-2">
                                <template x-for="talle in talles()" :key="talle">
                                    <button type="button" class="min-h-11 min-w-11 px-3 rounded-md border text-sm focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
                                        :class="seleccion.talle === talle ? 'border-indigo-600 bg-indigo-50 text-indigo-800 font-semibold' : 'border-gray-300 text-gray-800'"
                                        :aria-pressed="(seleccion.talle === talle).toString()" @click="elegirTalle(talle)" x-text="talle"></button>
                                </template>
                            </div>
                        </fieldset>

                        <fieldset x-show="colores().length > 0">
                            <legend class="text-sm text-gray-700">Color</legend>
                            <div class="mt-1 grid grid-cols-3 gap-2 sm:grid-cols-4">
                                <template x-for="color in colores()" :key="color.color_id">
                                    <button type="button" class="p-1 rounded-md border text-sm focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
                                        :class="seleccion.colorId === color.color_id ? 'border-indigo-600 bg-indigo-50' : 'border-gray-300'"
                                        :aria-pressed="(seleccion.colorId === color.color_id).toString()" @click="elegirColor(color.color_id)">
                                        <template x-if="color.foto">
                                            <img :src="color.foto" :alt="`${seleccion.producto.nombre} – ${color.nombre}`" class="aspect-square w-full rounded-sm object-cover bg-gray-100" loading="lazy">
                                        </template>
                                        <template x-if="!color.foto">
                                            <span class="flex aspect-square w-full items-center justify-center rounded-sm bg-gray-100 text-xs text-gray-500">Sin imagen</span>
                                        </template>
                                        <span class="mt-1 block text-gray-900" x-text="color.nombre"></span>
                                    </button>
                                </template>
                            </div>
                        </fieldset>

                        {{-- Several variants for the same color (sizes kept while the size parameter is off). --}}
                        <ul x-show="seleccion.colorId !== null && variantesElegibles(seleccion.colorId).length > 1" class="flex flex-wrap gap-2" aria-label="Variantes del color">
                            <template x-for="variante in (seleccion.colorId !== null ? variantesElegibles(seleccion.colorId) : [])" :key="variante.variante_id">
                                <li>
                                    <button type="button" class="{{ $botonSecundario }}" @click="agregar(variante, seleccion.producto)" x-text="variante.descripcion"></button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
            </section>

            {{-- Cart --}}
            <section aria-labelledby="carrito-titulo" class="space-y-2">
                <div class="px-4 sm:px-0 flex items-center justify-between gap-2">
                    <h3 id="carrito-titulo" class="font-medium text-gray-900">Carrito</h3>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500" x-model="mostrarDescuentos">
                        Descuentos
                    </label>
                </div>
                <p class="px-4 sm:px-0 text-sm text-red-600" x-show="error('items')" x-text="error('items')"></p>

                <ul class="space-y-2" aria-label="Productos de la venta">
                    <template x-for="(item, indice) in items" :key="item.variante_id">
                        <li class="p-3 bg-white shadow-sm sm:rounded-lg space-y-2">
                            <div class="flex items-start gap-3">
                                <template x-if="item.foto">
                                    <img :src="item.foto" :alt="`${item.producto} – ${item.descripcion}`" class="h-14 w-14 shrink-0 rounded-sm object-cover bg-gray-100">
                                </template>
                                <div class="min-w-0 grow">
                                    <p class="font-medium text-gray-900" x-text="item.producto"></p>
                                    <p class="text-sm text-gray-600" x-text="`${item.descripcion} · $ ${importe(item.precio_final)} · stock ${item.stock}`"></p>
                                </div>
                                <p class="text-end font-semibold text-gray-900" x-text="`$ ${importe(calculo?.lineas?.[indice]?.total ?? 0)}`"></p>
                            </div>
                            <div class="flex flex-wrap items-end gap-2">
                                <div class="flex items-center gap-1">
                                    <button type="button" class="{{ $botonSecundario }} min-w-11" @click="cambiarCantidad(item, -1)" :aria-label="`Una unidad menos de ${item.producto} ${item.descripcion}`">−</button>
                                    <label class="sr-only" :for="`cantidad-${indice}`">Cantidad</label>
                                    <input :id="`cantidad-${indice}`" type="number" inputmode="numeric" min="1" class="w-16 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs text-center" x-model="item.cantidad" @change="previsualizar()">
                                    <button type="button" class="{{ $botonSecundario }} min-w-11" @click="cambiarCantidad(item, 1)" :aria-label="`Una unidad más de ${item.producto} ${item.descripcion}`">+</button>
                                </div>
                                <div x-show="mostrarDescuentos">
                                    <label class="block text-xs text-gray-700" :for="`descuento-${indice}`">Descuento $</label>
                                    <input :id="`descuento-${indice}`" type="text" inputmode="decimal" class="w-28 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs" x-model="item.descuento" @input.debounce.400ms="previsualizar()">
                                </div>
                                <button type="button" class="ms-auto text-sm text-red-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500" @click="quitar(indice)">
                                    Quitar<span class="sr-only" x-text="` ${item.producto} ${item.descripcion}`"></span>
                                </button>
                            </div>
                            <p class="text-sm text-red-600" x-show="error(`items.${indice}.cantidad`) || error(`items.${indice}.descuento`) || error(`items.${indice}.variante_id`)"
                                x-text="error(`items.${indice}.cantidad`) || error(`items.${indice}.descuento`) || error(`items.${indice}.variante_id`)"></p>
                        </li>
                    </template>
                    <li class="p-4 bg-white shadow-sm sm:rounded-lg text-sm text-gray-600" x-show="items.length === 0">Buscá o escaneá un producto para empezar.</li>
                </ul>

                <div class="p-4 bg-white shadow-sm sm:rounded-lg" x-show="mostrarDescuentos">
                    <x-input-label for="descuento" value="Descuento general $" />
                    <x-text-input id="descuento" type="text" inputmode="decimal" class="mt-1 block w-40" x-model="descuento" @input.debounce.400ms="previsualizar()" />
                    <p class="mt-1 text-xs text-gray-600">Se reparte entre los productos. Todo descuento requiere la autorización de un usuario con permiso.</p>
                    <p class="mt-1 text-sm text-red-600" x-show="error('descuento')" x-text="error('descuento')"></p>
                </div>
            </section>

            {{-- Customer and seller --}}
            <section aria-labelledby="cliente-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg space-y-3">
                <h3 id="cliente-titulo" class="font-medium text-gray-900">Cliente y vendedor</h3>

                <div x-show="cliente" class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-indigo-50 p-3">
                    <p class="text-gray-900"><span x-text="cliente?.nombre"></span> <span class="text-sm text-gray-600" x-text="cliente?.documento"></span></p>
                    <button type="button" class="text-sm text-gray-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500" @click="quitarCliente()">Quitar cliente</button>
                </div>

                <div x-show="!cliente" class="space-y-2">
                    <div>
                        <x-input-label for="cliente-busqueda" value="Cliente (opcional: sin cliente es consumidor final)" />
                        <x-text-input id="cliente-busqueda" type="search" class="mt-1 block w-full" autocomplete="off" x-model="clienteBusqueda" @input="buscarClientes()" placeholder="Nombre, documento o teléfono" />
                    </div>
                    <ul class="divide-y divide-gray-100" x-show="clientes.length > 0" aria-label="Clientes encontrados">
                        <template x-for="encontrado in clientes" :key="encontrado.id">
                            <li>
                                <button type="button" class="w-full min-h-11 py-2 text-start rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500" @click="elegirCliente(encontrado)">
                                    <span class="text-gray-900" x-text="encontrado.nombre"></span>
                                    <span class="text-sm text-gray-600" x-text="encontrado.documento || encontrado.telefono"></span>
                                </button>
                            </li>
                        </template>
                    </ul>

                    @if ($puedeCrearClientes)
                        <details class="rounded-md border border-gray-200 p-3">
                            <summary class="cursor-pointer text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Alta rápida de cliente</summary>
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                                <div>
                                    <x-input-label for="cliente-nombre" value="Nombre" />
                                    <x-text-input id="cliente-nombre" type="text" maxlength="150" class="mt-1 block w-full" x-model="clienteNuevo.nombre" />
                                    <p class="mt-1 text-sm text-red-600" x-text="(erroresCliente.nombre ?? [])[0] ?? ''"></p>
                                </div>
                                <div>
                                    <x-input-label for="cliente-telefono" value="Teléfono" />
                                    <x-text-input id="cliente-telefono" type="tel" maxlength="50" class="mt-1 block w-full" x-model="clienteNuevo.telefono" />
                                    <p class="mt-1 text-sm text-red-600" x-text="(erroresCliente.telefono ?? [])[0] ?? ''"></p>
                                </div>
                                <div>
                                    <x-input-label for="cliente-documento" value="DNI o CUIT" />
                                    <x-text-input id="cliente-documento" type="text" inputmode="numeric" maxlength="20" class="mt-1 block w-full" x-model="clienteNuevo.dni_cuit" />
                                    <p class="mt-1 text-sm text-red-600" x-text="(erroresCliente.dni_cuit ?? [])[0] ?? ''"></p>
                                </div>
                            </div>
                            <button type="button" class="{{ $botonSecundario }} mt-2" @click="crearCliente()">Crear y elegir</button>
                        </details>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <x-input-label for="vendedor_id" value="Vendedor" />
                        <select id="vendedor_id" class="{{ $select }}" x-model="vendedorId" required>
                            <option value="">Elegí el vendedor</option>
                            @foreach ($vendedores as $vendedor)
                                <option value="{{ $vendedor->id }}">{{ $vendedor->user->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-sm text-red-600" x-show="error('vendedor_id')" x-text="error('vendedor_id')"></p>
                    </div>
                    @if ($puntosVenta->count() > 1)
                        <div>
                            <x-input-label for="punto_venta_id" value="Punto de venta" />
                            <select id="punto_venta_id" class="{{ $select }}" x-model="puntoVentaId">
                                @foreach ($puntosVenta as $puntoVenta)
                                    <option value="{{ $puntoVenta->id }}">{{ $puntoVenta->nombre() }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
                <p class="text-sm text-red-600" x-show="error('punto_venta_id') || error('cliente_id')" x-text="error('punto_venta_id') || error('cliente_id')"></p>
            </section>

            {{-- Payments --}}
            <section aria-labelledby="pagos-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg space-y-3">
                <h3 id="pagos-titulo" class="font-medium text-gray-900">Pagos</h3>
                <p class="text-sm text-red-600" x-show="error('pagos')" x-text="error('pagos')"></p>

                <template x-for="(pago, indice) in pagos" :key="indice">
                    <fieldset class="rounded-md border border-gray-200 p-3 space-y-2">
                        <legend class="px-1 text-sm text-gray-700" x-text="`Pago ${indice + 1}`"></legend>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div class="col-span-2">
                                <label class="block text-sm text-gray-700" :for="`medio-${indice}`">Medio</label>
                                <select :id="`medio-${indice}`" class="{{ $select }}" x-model="pago.medio_pago_id" @change="cambiarMedio(pago)">
                                    <template x-for="medio in medios" :key="medio.id">
                                        <option :value="String(medio.id)" x-text="medio.nombre" :selected="String(medio.id) === pago.medio_pago_id"></option>
                                    </template>
                                </select>
                            </div>
                            <div x-show="(medio(pago)?.planes ?? []).length > 0">
                                <label class="block text-sm text-gray-700" :for="`plan-${indice}`">Cuotas</label>
                                <select :id="`plan-${indice}`" class="{{ $select }}" x-model="pago.plan_cuota_id" @change="previsualizar()">
                                    <template x-for="plan in (medio(pago)?.planes ?? [])" :key="plan.id">
                                        <option :value="String(plan.id)" :selected="String(plan.id) === pago.plan_cuota_id"
                                            x-text="`${plan.cuotas} ${plan.cuotas === 1 ? 'cuota' : 'cuotas'}${Number.parseFloat(plan.recargo_pct) > 0 ? ` (+${plan.recargo_pct.replace('.', ',')} %)` : ''}`"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-700" :for="`importe-${indice}`">Importe $</label>
                                <input :id="`importe-${indice}`" type="text" inputmode="decimal" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs" x-model="pago.importe" @input.debounce.400ms="previsualizar()">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3" x-show="medio(pago)?.requiere_referencia || medio(pago)?.admite_lote">
                            <div>
                                <label class="block text-sm text-gray-700" :for="`referencia-${indice}`">Referencia</label>
                                <input :id="`referencia-${indice}`" type="text" maxlength="100" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs" x-model="pago.referencia">
                            </div>
                            <div x-show="medio(pago)?.admite_lote">
                                <label class="block text-sm text-gray-700" :for="`lote-${indice}`">Lote</label>
                                <input :id="`lote-${indice}`" type="text" maxlength="20" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs" x-model="pago.lote">
                            </div>
                            <div x-show="medio(pago)?.admite_lote">
                                <label class="block text-sm text-gray-700" :for="`cupon-${indice}`">Cupón</label>
                                <input :id="`cupon-${indice}`" type="text" maxlength="20" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs" x-model="pago.cupon">
                            </div>
                        </div>
                        <p class="text-sm text-gray-700" x-show="Number.parseFloat(recargoDe(indice)) > 0" x-text="`Recargo por cuotas: $ ${importe(recargoDe(indice))} · se cobran $ ${importe(calculo?.pagos?.[indice]?.cobrado)}`"></p>
                        <p class="text-sm text-amber-800" x-show="medio(pago)?.requiere_cliente && !cliente">Elegí el cliente para cobrar con este medio.</p>
                        <p class="text-sm text-red-600" x-text="error(`pagos.${indice}.medio_pago_id`) || error(`pagos.${indice}.plan_cuota_id`) || error(`pagos.${indice}.importe`) || error(`pagos.${indice}.referencia`)"></p>
                        <div class="flex flex-wrap gap-3">
                            <button type="button" class="text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500" x-show="pendiente !== 0 && !Number.isNaN(pendiente)" @click="completar(pago)">Completar con lo que falta</button>
                            <button type="button" class="text-sm text-red-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500" x-show="pagos.length > 1" @click="quitarPago(indice)">Quitar pago</button>
                        </div>
                    </fieldset>
                </template>

                <button type="button" class="{{ $botonSecundario }}" @click="agregarPago()" x-show="pagos.length < 10">Agregar otro medio de pago</button>
            </section>

            {{-- Discount authorization --}}
            <section aria-labelledby="autorizacion-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg space-y-2" x-show="requiereAutorizacion">
                <h3 id="autorizacion-titulo" class="font-medium text-gray-900">Autorización del descuento</h3>
                <p class="text-sm text-gray-600">Un usuario con permiso para autorizar descuentos (por ejemplo, el Gerente) ingresa su email y contraseña.</p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <div>
                        <x-input-label for="autorizacion-email" value="Email de quien autoriza" />
                        <x-text-input id="autorizacion-email" type="email" autocomplete="off" class="mt-1 block w-full" x-model="autorizacion.email" />
                    </div>
                    <div>
                        <x-input-label for="autorizacion-password" value="Contraseña" />
                        <x-text-input id="autorizacion-password" type="password" autocomplete="off" class="mt-1 block w-full" x-model="autorizacion.password" />
                    </div>
                </div>
                <p class="text-sm text-red-600" x-text="error('autorizacion') || error('autorizacion.email')"></p>
            </section>

            {{-- Totals --}}
            <section aria-labelledby="totales-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg space-y-3 sm:sticky sm:bottom-0">
                <h3 id="totales-titulo" class="sr-only">Totales</h3>
                <dl class="grid grid-cols-2 gap-y-1 text-sm" x-show="calculo">
                    <dt class="text-gray-600" x-show="Number.parseFloat(calculo?.descuento) > 0">Descuentos</dt>
                    <dd class="text-end text-gray-900" x-show="Number.parseFloat(calculo?.descuento) > 0" x-text="`− $ ${importe(calculo?.descuento)}`"></dd>
                    <dt class="text-gray-600" x-show="Number.parseFloat(calculo?.recargo) > 0">Recargo por cuotas</dt>
                    <dd class="text-end text-gray-900" x-show="Number.parseFloat(calculo?.recargo) > 0" x-text="`$ ${importe(calculo?.recargo)}`"></dd>
                    <dt class="text-gray-600">IVA incluido</dt>
                    <dd class="text-end text-gray-900" x-text="`$ ${importe(calculo?.iva)}`"></dd>
                    <dt class="text-lg font-semibold text-gray-900">Total</dt>
                    <dd class="text-end text-lg font-semibold text-gray-900" x-text="`$ ${importe(calculo?.total)}`"></dd>
                    <dt class="text-gray-600" x-show="pendiente !== 0">Falta pagar</dt>
                    <dd class="text-end font-medium" :class="pendiente > 0 ? 'text-amber-800' : 'text-red-700'" x-show="pendiente !== 0" x-text="pendiente > 0 ? `$ ${importe(calculo?.pendiente)}` : `Sobran $ ${importe(-pendiente)}`"></dd>
                </dl>
                <button type="button" class="w-full min-h-12 inline-flex items-center justify-center px-4 py-3 bg-indigo-700 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-800 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="!listo" @click="confirmar()">
                    <span x-text="enviando ? 'Registrando…' : 'Confirmar venta'"></span>
                </button>
            </section>
        </div>
    </div>
</x-app-layout>
