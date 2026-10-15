@php
    use App\Services\Formato;
    use App\Services\Importes;

    $esTicket = $formato === 'ticket';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $datos['titulo'] }} {{ $datos['numero_completo'] }}</title>
    {{-- Self-contained print stylesheet: the document must look the same whatever the app CSS does. --}}
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; color: #111827; font-family: ui-sans-serif, system-ui, sans-serif; }
        .acciones { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: center; padding: 1rem; }
        .acciones a, .acciones button { font: inherit; font-size: .875rem; padding: .5rem 1rem; border-radius: .375rem; border: 1px solid #d1d5db; background: #fff; color: #111827; text-decoration: none; cursor: pointer; }
        .acciones button { background: #1f2937; color: #fff; border-color: #1f2937; }
        .acciones a:focus-visible, .acciones button:focus-visible { outline: 2px solid #6366f1; outline-offset: 2px; }
        .hoja { background: #fff; margin: 0 auto 2rem; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,.15); }
        .ticket { width: 80mm; max-width: 100%; font-size: 12px; }
        .a4 { width: 210mm; max-width: 100%; min-height: 297mm; padding: 15mm; font-size: 13px; }
        h1 { font-size: 1.15em; margin: 0; }
        .leyenda { margin: .5rem 0; padding: .35rem; border: 2px solid #111827; text-align: center; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; }
        .centro { text-align: center; }
        .derecha { text-align: right; }
        .tenue { color: #4b5563; }
        .bloque { margin-top: .75rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: .2rem 0; vertical-align: top; }
        .a4 th, .a4 td { padding: .35rem .25rem; border-bottom: 1px solid #e5e7eb; }
        thead th { text-align: left; border-bottom: 1px solid #111827; }
        .total td { font-weight: 700; font-size: 1.15em; border-top: 1px solid #111827; }
        .anulacion { margin-top: .5rem; padding: .35rem; background: #fee2e2; color: #991b1b; }
        @media print {
            body { background: #fff; }
            .acciones { display: none; }
            .hoja { box-shadow: none; margin: 0; }
            .anulacion { background: none; border: 1px solid #111827; color: #111827; }
        }
        @page { size: {{ $esTicket ? '80mm auto' : 'A4' }}; margin: 0; }
    </style>
</head>
<body>
    <nav class="acciones" aria-label="Acciones del comprobante">
        <button type="button" onclick="window.print()">Imprimir o guardar PDF</button>
        <a href="{{ route('comprobantes.show', [$comprobante, 'formato' => $esTicket ? 'a4' : 'ticket']) }}">Ver en {{ $esTicket ? 'A4' : 'ticket' }}</a>
        @if ($comprobante->devolucion_id)
            <a href="{{ route('ventas.devoluciones.show', $comprobante->devolucion_id) }}">Volver a la devolución</a>
        @elseif ($comprobante->venta_id)
            <a href="{{ route('ventas.show', $comprobante->venta_id) }}">Volver a la venta</a>
        @endif
    </nav>

    <main class="hoja {{ $esTicket ? 'ticket' : 'a4' }}">
        <header class="{{ $esTicket ? 'centro' : '' }}">
            <h1>{{ $datos['comercio'] }}</h1>
            <p class="tenue">{{ $datos['sucursal']['nombre'] }}@if ($datos['sucursal']['domicilio']) · {{ $datos['sucursal']['domicilio'] }}@endif</p>
            <p class="leyenda">{{ $datos['leyenda'] }}</p>
            <p><strong>{{ $datos['titulo'] }}</strong><br>N° {{ $datos['numero_completo'] }}</p>
            <p class="tenue">{{ \Illuminate\Support\Carbon::parse($datos['fecha'])->format('d/m/Y') }} {{ $datos['hora'] }} · Venta N° {{ $datos['venta_id'] }}</p>
        </header>

        @isset($datos['anula'])
            <p class="anulacion">
                Anula el comprobante N° {{ $datos['anula']['numero_completo'] }} del {{ \Illuminate\Support\Carbon::parse($datos['anula']['fecha'])->format('d/m/Y') }}.
                Motivo: {{ $datos['motivo'] }}
            </p>
        @endisset

        @isset($datos['devolucion'])
            <p class="tenue">
                Devolución N° {{ $datos['devolucion']['id'] }} · {{ $datos['devolucion']['resolucion'] }}<br>
                Motivo: {{ $datos['devolucion']['motivo'] }}
                @if (! empty($datos['referencia']))
                    <br>Comprobante de venta: {{ $datos['referencia']['numero_completo'] }} del {{ \Illuminate\Support\Carbon::parse($datos['referencia']['fecha'])->format('d/m/Y') }}
                @endif
            </p>
        @endisset

        <section class="bloque">
            <p>
                Cliente: {{ $datos['cliente']['nombre'] ?? 'Consumidor final' }}
                @if (! empty($datos['cliente']['documento']))
                    · {{ $datos['cliente']['documento'] }}
                @endif
                @if (! empty($datos['cliente']['condicion_iva']))
                    <br><span class="tenue">{{ $datos['cliente']['condicion_iva'] }}</span>
                @endif
            </p>
            <p>Vendedor: {{ $datos['vendedor'] }}</p>
        </section>

        <section class="bloque">
            <table>
                <thead>
                    <tr>
                        <th scope="col">{{ $esTicket ? 'Cant. / Descripción' : 'Descripción' }}</th>
                        @unless ($esTicket)
                            <th scope="col" class="derecha">Cant.</th>
                            <th scope="col" class="derecha">Precio</th>
                            <th scope="col" class="derecha">Desc.</th>
                            <th scope="col" class="derecha">IVA</th>
                        @endunless
                        <th scope="col" class="derecha">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($datos['items'] as $item)
                        <tr>
                            <td>
                                @if ($esTicket)
                                    {{ $item['cantidad'] }} × $ {{ Formato::importe($item['precio_final']) }}<br>
                                @endif
                                {{ $item['descripcion'] }}
                                @if ($esTicket && Importes::aCentavos($item['descuento']) > 0)
                                    <br><span class="tenue">Descuento $ {{ Formato::importe($item['descuento']) }}</span>
                                @endif
                            </td>
                            @unless ($esTicket)
                                <td class="derecha">{{ $item['cantidad'] }}</td>
                                <td class="derecha">$ {{ Formato::importe($item['precio_final']) }}</td>
                                <td class="derecha">{{ Importes::aCentavos($item['descuento']) > 0 ? '$ '.Formato::importe($item['descuento']) : '—' }}</td>
                                <td class="derecha">{{ Formato::porcentaje($item['alicuota']) }}</td>
                            @endunless
                            <td class="derecha">$ {{ Formato::importe($item['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="bloque">
            <table>
                <tbody>
                    @if (Importes::aCentavos($datos['totales']['descuento']) > 0)
                        <tr><td>Descuentos</td><td class="derecha">− $ {{ Formato::importe($datos['totales']['descuento']) }}</td></tr>
                    @endif
                    @if (Importes::aCentavos($datos['totales']['recargo']) > 0)
                        <tr><td>Recargo por cuotas</td><td class="derecha">$ {{ Formato::importe($datos['totales']['recargo']) }}</td></tr>
                    @endif
                    @foreach ($datos['totales']['iva_por_alicuota'] as $fila)
                        <tr class="tenue"><td>Neto gravado {{ Formato::porcentaje($fila['alicuota']) }} · IVA</td><td class="derecha">$ {{ Formato::importe($fila['neto']) }} · $ {{ Formato::importe($fila['iva']) }}</td></tr>
                    @endforeach
                    <tr class="total"><td>Total</td><td class="derecha">$ {{ Formato::importe($datos['totales']['total']) }}</td></tr>
                </tbody>
            </table>
        </section>

        <section class="bloque">
            <p><strong>{{ isset($datos['devolucion']) ? 'Reintegro' : 'Pagos' }}</strong></p>
            <table>
                <tbody>
                    @foreach ($datos['pagos'] as $pago)
                        <tr>
                            <td>
                                {{ $pago['medio'] }}
                                @if ($pago['cuotas'])
                                    · {{ $pago['cuotas'] }} {{ $pago['cuotas'] === 1 ? 'cuota' : 'cuotas' }}
                                @endif
                                @if ($pago['referencia'])
                                    <br><span class="tenue">Ref. {{ $pago['referencia'] }}</span>
                                @endif
                            </td>
                            <td class="derecha">$ {{ Formato::importe($pago['cobrado']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <footer class="bloque centro">
            <p class="leyenda">{{ $datos['leyenda'] }}</p>
        </footer>
    </main>
</body>
</html>
