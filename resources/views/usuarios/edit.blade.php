<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar usuario</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('usuarios.update', $usuario) }}" class="p-4 sm:p-8 bg-white shadow-sm sm:rounded-lg">
                @csrf
                @method('PUT')

                @include('usuarios.partials.form', [
                    'usuario' => $usuario,
                    'sucursales' => $sucursales,
                    'opcionesRol' => $opcionesRol,
                    'rolesActuales' => $rolesActuales,
                ])

                <div class="mt-6 flex items-center gap-4">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <a href="{{ route('usuarios.index') }}" class="text-sm text-gray-600 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
