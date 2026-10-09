<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Auditoría</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="GET" action="{{ route('auditoria.index') }}" class="p-4 sm:p-6 bg-white shadow-sm sm:rounded-lg grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                <div>
                    <x-input-label for="desde" value="Desde" />
                    <x-text-input id="desde" name="desde" type="date" class="mt-1 block w-full" :value="$filtros['desde'] ?? ''" />
                    <x-input-error :messages="$errors->get('desde')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="hasta" value="Hasta" />
                    <x-text-input id="hasta" name="hasta" type="date" class="mt-1 block w-full" :value="$filtros['hasta'] ?? ''" />
                    <x-input-error :messages="$errors->get('hasta')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="user_id" value="Usuario" />
                    <select id="user_id" name="user_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs">
                        <option value="">Todos</option>
                        @foreach ($usuarios as $usuario)
                            <option value="{{ $usuario->id }}" @selected(($filtros['user_id'] ?? null) == $usuario->id)>{{ $usuario->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="accion" value="Acción" />
                    <select id="accion" name="accion" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-xs">
                        <option value="">Todas</option>
                        @foreach ($acciones as $accion)
                            <option value="{{ $accion }}" @selected(($filtros['accion'] ?? null) === $accion)>{{ $accion }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <x-primary-button>Filtrar</x-primary-button>
                    <a href="{{ route('auditoria.index') }}" class="inline-flex items-center px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-indigo-500">Limpiar</a>
                </div>
            </form>

            <section aria-label="Registros de auditoría" class="space-y-3">
                @forelse ($registros as $registro)
                    <article class="p-4 bg-white shadow-sm sm:rounded-lg">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="font-medium text-gray-900">{{ $registro->accion }}</p>
                            <time datetime="{{ $registro->created_at->toIso8601String() }}" class="text-sm text-gray-600">
                                {{ $registro->created_at->format('d/m/Y H:i:s') }}
                            </time>
                        </div>
                        <p class="mt-1 text-sm text-gray-700">
                            {{ $registro->user?->name ?? 'Sistema' }}
                            @if ($registro->modelo)
                                · {{ class_basename($registro->modelo) }} #{{ $registro->modelo_id }}
                            @endif
                        </p>

                        @php($campos = array_unique(array_merge(array_keys($registro->antes ?? []), array_keys($registro->despues ?? []))))
                        @if ($campos)
                            <dl class="mt-2 grid grid-cols-1 gap-1 text-sm">
                                @foreach ($campos as $campo)
                                    <div class="flex flex-wrap gap-x-2">
                                        <dt class="text-gray-600">{{ $campo }}:</dt>
                                        <dd class="text-gray-900">
                                            @if (array_key_exists($campo, $registro->antes ?? []))
                                                <span class="line-through text-gray-500">{{ is_scalar($registro->antes[$campo]) ? $registro->antes[$campo] : json_encode($registro->antes[$campo], JSON_UNESCAPED_UNICODE) }}</span>
                                                →
                                            @endif
                                            {{ is_scalar($registro->despues[$campo] ?? null) ? $registro->despues[$campo] : json_encode($registro->despues[$campo] ?? null, JSON_UNESCAPED_UNICODE) }}
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif

                        <p class="mt-2 text-xs text-gray-500">IP {{ $registro->ip }} · Dispositivo {{ \Illuminate\Support\Str::limit((string) $registro->dispositivo, 8, '') }}</p>
                    </article>
                @empty
                    <p class="p-4 bg-white shadow-sm sm:rounded-lg text-gray-600">No hay registros para los filtros elegidos.</p>
                @endforelse
            </section>

            {{ $registros->links() }}
        </div>
    </div>
</x-app-layout>
