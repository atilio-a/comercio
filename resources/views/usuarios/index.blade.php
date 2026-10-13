<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Usuarios</h2>

            @can('create', App\Models\User::class)
                <a href="{{ route('usuarios.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Nuevo usuario
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-3">
            @if (session('status'))
                <p role="status" class="p-4 bg-green-50 text-green-800 sm:rounded-lg">{{ session('status') }}</p>
            @endif

            <ul class="space-y-3" aria-label="Usuarios">
                @foreach ($usuarios as $usuario)
                    <li class="p-4 bg-white shadow-sm sm:rounded-lg flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900">
                                {{ $usuario->name }}
                                @if ($usuario->esVendedor())
                                    <span class="ms-1 rounded-sm bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-800">Vendedor</span>
                                @endif
                                @unless ($usuario->activo)
                                    <span class="ms-1 rounded-sm bg-red-100 px-1.5 py-0.5 text-xs text-red-800">Inactivo</span>
                                @endunless
                            </p>
                            <p class="text-sm text-gray-600 break-all">{{ $usuario->email }}</p>
                            <ul class="mt-1 text-sm text-gray-700">
                                @foreach ($usuario->sucursales as $sucursal)
                                    <li>{{ $sucursal->nombre }}: {{ $roles[$usuario->id][$sucursal->id] ?? 'Sin rol' }}</li>
                                @endforeach
                            </ul>
                        </div>

                        @can('update', $usuario)
                            <a href="{{ route('usuarios.edit', $usuario) }}" class="text-sm text-indigo-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                                Editar<span class="sr-only"> {{ $usuario->name }}</span>
                            </a>
                        @endcan
                    </li>
                @endforeach
            </ul>

            {{ $usuarios->links() }}
        </div>
    </div>
</x-app-layout>
