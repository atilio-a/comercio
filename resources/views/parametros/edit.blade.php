<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Parámetros</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="p-4 sm:p-8 bg-white shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('parametros.update') }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <x-input-error :messages="$errors->get('parametros')" />

                    @foreach ($definiciones as $parametro)
                        @php($campo = 'parametros['.$parametro->value.']')
                        @php($id = 'parametro-'.$parametro->value)
                        @php($disponible = $parametro->sePuedeHabilitar())

                        <div class="flex items-start gap-3">
                            <input type="hidden" name="{{ $campo }}" value="0">
                            <input type="checkbox" id="{{ $id }}" name="{{ $campo }}" value="1"
                                class="mt-1 rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500 disabled:opacity-50"
                                aria-describedby="{{ $id }}-ayuda"
                                @checked(old('parametros.'.$parametro->value, $valores[$parametro->value]))
                                @disabled(! $disponible)>

                            <div>
                                <label for="{{ $id }}" class="font-medium text-gray-900">
                                    {{ $parametro->etiqueta() }}
                                    @unless ($disponible)
                                        <span class="ms-1 rounded-sm bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">No disponible</span>
                                    @endunless
                                </label>
                                <p id="{{ $id }}-ayuda" class="text-sm text-gray-600">{{ $parametro->ayuda() }}</p>
                                <x-input-error :messages="$errors->get('parametros.'.$parametro->value)" class="mt-1" />
                            </div>
                        </div>
                    @endforeach

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Save') }}</x-primary-button>

                        @if (session('status') === 'parametros-guardados')
                            <p role="status" class="text-sm text-gray-600">{{ __('Saved.') }}</p>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
