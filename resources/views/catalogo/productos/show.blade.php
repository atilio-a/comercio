@php($formato = App\Services\Formato::class)

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $producto->nombre }}
                @unless ($producto->activo)
                    <span class="ms-1 rounded-sm bg-red-100 px-1.5 py-0.5 text-xs text-red-800 align-middle">Inactivo</span>
                @endunless
            </h2>

            <div class="flex flex-wrap gap-2">
                @can('stock.ajustar')
                    <a href="{{ route('stock.ajuste.edit', $producto) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Ajustar stock
                    </a>
                @endcan
                @can('update', $producto)
                    <a href="{{ route('catalogo.productos.variantes.create', $producto) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Agregar variantes
                    </a>
                    <a href="{{ route('catalogo.productos.edit', $producto) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Editar producto
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            <dl class="p-4 bg-white shadow-sm sm:rounded-lg grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div><dt class="text-gray-600">Categoría</dt><dd class="text-gray-900">{{ $producto->categoria->nombre }}</dd></div>
                <div><dt class="text-gray-600">Marca</dt><dd class="text-gray-900">{{ $producto->marca?->nombre ?? '—' }}</dd></div>
                <div><dt class="text-gray-600">Temporada</dt><dd class="text-gray-900">{{ $producto->temporada?->nombre ?? '—' }}</dd></div>
                <div><dt class="text-gray-600">IVA</dt><dd class="text-gray-900">{{ $formato::porcentaje($producto->alicuotaIva->porcentaje) }}</dd></div>
                @if ($producto->descripcion)
                    <div class="col-span-2 sm:col-span-4"><dt class="text-gray-600">Descripción</dt><dd class="text-gray-900">{{ $producto->descripcion }}</dd></div>
                @endif
            </dl>

            @include('catalogo.productos.partials.galeria')

            <h3 class="font-medium text-gray-900 px-4 sm:px-0">Variantes ({{ $variantes->count() }})</h3>

            <ul class="space-y-3" aria-label="Variantes">
                @foreach ($variantes as $variante)
                    @php($desglose = $desgloses[$variante->id])
                    <li class="p-4 bg-white shadow-sm sm:rounded-lg">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="font-medium text-gray-900">
                                {{ $variante->descripcion() }}
                                @unless ($variante->activo)
                                    <span class="ms-1 rounded-sm bg-red-100 px-1.5 py-0.5 text-xs text-red-800">Inactiva</span>
                                @endunless
                            </p>
                            <div class="flex flex-wrap gap-3 text-sm">
                                @can('stock.ver')
                                    <a href="{{ route('stock.kardex', $variante) }}" class="text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                        Kardex<span class="sr-only"> {{ $variante->descripcion() }}</span>
                                    </a>
                                @endcan
                                @can('update', $producto)
                                    <a href="{{ route('catalogo.productos.variantes.edit', [$producto, $variante]) }}" class="text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                        Editar<span class="sr-only"> {{ $variante->descripcion() }}</span>
                                    </a>
                                @endcan
                            </div>
                        </div>
                        <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-6">
                            <div><dt class="text-gray-600">SKU</dt><dd class="text-gray-900 break-all">{{ $variante->sku }}</dd></div>
                            <div><dt class="text-gray-600">Código</dt><dd class="text-gray-900 break-all">{{ $variante->codigo_barras }}</dd></div>
                            <div><dt class="text-gray-600">Costo</dt><dd class="text-gray-900">$ {{ $formato::importe($variante->costo) }}</dd></div>
                            <div><dt class="text-gray-600">Precio final</dt><dd class="font-medium text-gray-900">$ {{ $formato::importe($variante->precio_final) }}</dd></div>
                            <div><dt class="text-gray-600">Neto</dt><dd class="text-gray-900">$ {{ $formato::importe($desglose['neto']) }}</dd></div>
                            <div><dt class="text-gray-600">IVA</dt><dd class="text-gray-900">$ {{ $formato::importe($desglose['iva']) }}</dd></div>
                        </dl>
                    </li>
                @endforeach
            </ul>

            <a href="{{ route('catalogo.productos.index') }}" class="inline-block px-4 sm:px-0 text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Volver al listado</a>
        </div>
    </div>
</x-app-layout>
