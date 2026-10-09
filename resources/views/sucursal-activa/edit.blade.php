<x-guest-layout>
    <form method="POST" action="{{ route('sucursal-activa.update') }}">
        @csrf
        @method('PUT')

        <fieldset>
            <legend class="text-lg font-medium text-gray-900">Elegí la sucursal</legend>
            <p class="mt-1 text-sm text-gray-600">Vas a operar en esta sucursal hasta que la cambies.</p>

            <div class="mt-4 space-y-2">
                @foreach ($sucursales as $sucursal)
                    <label class="flex items-center gap-3 rounded-md border border-gray-300 p-3 has-checked:border-indigo-500 has-checked:bg-indigo-50">
                        <input type="radio" name="sucursal_id" value="{{ $sucursal->id }}"
                            class="border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            @checked(old('sucursal_id', $actual) == $sucursal->id) required>
                        <span class="text-gray-900">{{ $sucursal->nombre }}</span>
                    </label>
                @endforeach
            </div>

            <x-input-error :messages="$errors->get('sucursal_id')" class="mt-2" />
        </fieldset>

        <div class="mt-6 flex items-center justify-between">
            <button form="logout-form" type="submit" class="text-sm text-gray-600 underline hover:text-gray-900 rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('Log Out') }}
            </button>

            <x-primary-button>Continuar</x-primary-button>
        </div>
    </form>

    <form id="logout-form" method="POST" action="{{ route('logout') }}">
        @csrf
    </form>
</x-guest-layout>
