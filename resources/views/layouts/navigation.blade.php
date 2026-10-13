@php
    /*
     * Grouped menu: each entry keeps its own permission check and a group without
     * visible entries is not rendered. Shared by the desktop dropdowns and the mobile sections.
     */
    $usuarioMenu = auth()->user();
    $gruposMenu = collect([
        'ventas' => ['titulo' => 'Ventas y caja', 'entradas' => [
            ['visible' => $usuarioMenu->can('caja.operar'), 'url' => route('caja.mi-caja'), 'activo' => request()->routeIs('caja.mi-caja', 'caja.cierre.*'), 'texto' => 'Caja'],
            ['visible' => $usuarioMenu->can('caja.supervisar'), 'url' => route('caja.sesiones.index'), 'activo' => request()->routeIs('caja.sesiones.*'), 'texto' => 'Sesiones de caja'],
            ['visible' => $usuarioMenu->can('caja.configurar'), 'url' => route('caja.medios-pago.index'), 'activo' => request()->routeIs('caja.medios-pago.*', 'caja.cajas.*'), 'texto' => 'Medios de pago y cajas'],
        ]],
        'inventario' => ['titulo' => 'Inventario', 'entradas' => [
            ['visible' => $usuarioMenu->can('catalogo.ver'), 'url' => route('catalogo.productos.index'), 'activo' => request()->routeIs('catalogo.productos.*'), 'texto' => 'Catálogo'],
            ['visible' => $usuarioMenu->can('stock.ver'), 'url' => route('stock.index'), 'activo' => request()->routeIs('stock.*'), 'texto' => 'Stock'],
            ['visible' => $usuarioMenu->can('compras.ver'), 'url' => route('compras.ordenes.index'), 'activo' => request()->routeIs('compras.*'), 'texto' => 'Compras'],
        ]],
        'administracion' => ['titulo' => 'Administración', 'entradas' => [
            ['visible' => $usuarioMenu->can('viewAny', App\Models\User::class), 'url' => route('usuarios.index'), 'activo' => request()->routeIs('usuarios.*'), 'texto' => 'Usuarios'],
            ['visible' => $usuarioMenu->can('parametros.editar'), 'url' => route('parametros.edit'), 'activo' => request()->routeIs('parametros.*'), 'texto' => 'Parámetros'],
            ['visible' => $usuarioMenu->can('auditoria.ver'), 'url' => route('auditoria.index'), 'activo' => request()->routeIs('auditoria.*'), 'texto' => 'Auditoría'],
            ['visible' => $usuarioMenu->can('catalogo.gestionar'), 'url' => route('catalogo.maestros.index', App\Enums\TablaMaestra::Categorias), 'activo' => request()->routeIs('catalogo.maestros.*', 'catalogo.alicuotas.*'), 'texto' => 'Tablas del catálogo'],
        ]],
    ])
        ->map(function (array $grupo) {
            $grupo['entradas'] = array_values(array_filter($grupo['entradas'], fn (array $entrada) => $entrada['visible']));
            $grupo['activo'] = collect($grupo['entradas'])->contains('activo', true);

            return $grupo;
        })
        ->filter(fn (array $grupo) => $grupo['entradas'] !== []);
@endphp

<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-6 sm:-my-px sm:ms-8 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    @foreach ($gruposMenu as $clave => $grupo)
                        <div class="flex items-center">
                            <x-dropdown align="left" width="56">
                                <x-slot name="trigger">
                                    <button type="button" x-bind:aria-expanded="open.toString()" aria-haspopup="true" id="menu-{{ $clave }}"
                                        class="inline-flex items-center gap-1 px-1 py-2 border-b-2 text-sm font-medium leading-5 rounded-t-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500 transition duration-150 ease-in-out {{ $grupo['activo'] ? 'border-indigo-400 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                                        {{ $grupo['titulo'] }}
                                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    @foreach ($grupo['entradas'] as $entrada)
                                        <x-dropdown-link :href="$entrada['url']" :aria-current="$entrada['activo'] ? 'page' : false"
                                            :class="$entrada['activo'] ? 'font-semibold text-indigo-700 bg-indigo-50' : ''">
                                            {{ $entrada['texto'] }}
                                        </x-dropdown-link>
                                    @endforeach
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                @if ($multiSucursal && $sucursalActiva)
                    <a href="{{ route('sucursal-activa.edit') }}" class="me-4 text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                        Sucursal: <span class="font-medium">{{ $sucursalActiva->nombre }}</span><span class="sr-only"> (cambiar)</span>
                    </a>
                @endif

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-hidden transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-controls="menu-movil" aria-label="Menú" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div id="menu-movil" :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            @foreach ($gruposMenu as $clave => $grupo)
                <div role="group" aria-labelledby="seccion-{{ $clave }}" class="pt-2">
                    <p id="seccion-{{ $clave }}" class="px-4 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $grupo['titulo'] }}</p>
                    @foreach ($grupo['entradas'] as $entrada)
                        <x-responsive-nav-link :href="$entrada['url']" :active="$entrada['activo']" :aria-current="$entrada['activo'] ? 'page' : false">
                            {{ $entrada['texto'] }}
                        </x-responsive-nav-link>
                    @endforeach
                </div>
            @endforeach
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                @if ($multiSucursal && $sucursalActiva)
                    <x-responsive-nav-link :href="route('sucursal-activa.edit')">
                        Sucursal: {{ $sucursalActiva->nombre }} (cambiar)
                    </x-responsive-nav-link>
                @endif

                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
