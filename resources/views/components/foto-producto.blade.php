@props(['foto' => null, 'tamano' => 'miniatura', 'alt' => ''])

{{-- Imagen de producto servida desde el disco privado, o un marcador neutro si no hay. --}}
@if ($foto)
    <img src="{{ route('catalogo.fotos.mostrar', [$foto, $tamano]) }}"
        alt="{{ $alt !== '' ? $alt : $foto->textoAlternativo() }}"
        loading="lazy" decoding="async"
        {{ $attributes->merge(['class' => 'object-cover bg-gray-100']) }}>
@else
    <div role="img" aria-label="Sin imagen{{ $alt !== '' ? ' de '.$alt : '' }}"
        {{ $attributes->merge(['class' => 'flex items-center justify-center bg-gray-100 text-gray-400']) }}>
        <svg class="h-1/2 w-1/2 max-h-10 max-w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v13.5a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V9.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
        </svg>
    </div>
@endif
