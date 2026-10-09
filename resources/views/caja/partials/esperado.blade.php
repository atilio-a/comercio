{{-- Saldo esperado por medio de pago. Variable: $esperado (list de medio, esperado, cuenta). --}}
<section aria-labelledby="esperado-titulo" class="p-4 bg-white shadow-sm sm:rounded-lg">
    <h3 id="esperado-titulo" class="font-semibold text-gray-900">Saldo esperado</h3>
    <dl class="mt-2 divide-y divide-gray-100">
        @forelse ($esperado as $fila)
            <div class="flex items-center justify-between gap-3 py-2">
                <dt class="text-gray-700">
                    {{ $fila['medio']->nombre }}
                    @unless ($fila['cuenta'])
                        <span class="text-xs text-gray-500">(no se cuenta)</span>
                    @endunless
                </dt>
                <dd class="font-semibold {{ str_starts_with($fila['esperado'], '-') ? 'text-red-700' : 'text-gray-900' }}">
                    $ {{ App\Services\Formato::importe($fila['esperado']) }}
                </dd>
            </div>
        @empty
            <p class="py-2 text-sm text-gray-600">Sin movimientos.</p>
        @endforelse
    </dl>
</section>
