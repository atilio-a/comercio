<?php

namespace App\Services;

use App\Models\Color;
use App\Models\Producto;
use App\Models\Variante;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Genera las variantes de un producto a partir de una grilla de colores y talles.
 */
class GeneradorVariantes
{
    public const MAXIMO_COMBINACIONES = 200;

    public const MAXIMO_POR_RANGO = 30;

    public function __construct(
        private GeneradorCodigos $codigos,
    ) {}

    /**
     * Interpreta talles separados por coma, con rangos numéricos: "35-38, 40, S" → 35, 36, 37, 38, 40, S.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException con un mensaje para mostrar al usuario.
     */
    public static function parsearTalles(string $entrada): array
    {
        $talles = [];

        foreach (explode(',', $entrada) as $parte) {
            $parte = trim($parte);

            if ($parte === '') {
                continue;
            }

            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $parte, $rango)) {
                [$desde, $hasta] = [(int) $rango[1], (int) $rango[2]];

                if ($desde > $hasta || $hasta - $desde + 1 > self::MAXIMO_POR_RANGO) {
                    throw new InvalidArgumentException("El rango \"{$parte}\" no es válido: el inicio debe ser menor o igual al fin y abarcar como máximo ".self::MAXIMO_POR_RANGO.' talles.');
                }

                foreach (range($desde, $hasta) as $talle) {
                    $talles[] = (string) $talle;
                }

                continue;
            }

            if (! preg_match('/^[A-Za-z0-9.\/]{1,10}$/', $parte)) {
                throw new InvalidArgumentException("El talle \"{$parte}\" no es válido: usá hasta 10 letras, números, punto o barra.");
            }

            $talles[] = strtoupper($parte);
        }

        return array_values(array_unique($talles));
    }

    /**
     * Cantidad de variantes que genera la grilla.
     *
     * @param  list<string>|null  $talles  null cuando los talles están deshabilitados.
     */
    public static function cantidad(int $colores, ?array $talles): int
    {
        return $colores * ($talles === null ? 1 : count($talles));
    }

    /**
     * Crea las combinaciones que todavía no existen. Debe llamarse dentro de una transacción
     * (reserva códigos de barras).
     *
     * @param  Collection<int, Color>  $colores
     * @param  list<string>|null  $talles  null cuando los talles están deshabilitados.
     * @return array{creadas: list<Variante>, omitidas: list<string>}
     */
    public function crear(Producto $producto, Collection $colores, ?array $talles, string $costo, string $precioFinal): array
    {
        $existentes = $producto->variantes()
            ->get(['color_id', 'talle'])
            ->map(fn (Variante $variante) => $variante->color_id.'|'.($variante->talle ?? ''))
            ->flip();

        $creadas = [];
        $omitidas = [];

        foreach ($colores as $color) {
            foreach ($talles ?? [null] as $talle) {
                $descripcion = $color->nombre.($talle !== null ? ' / '.$talle : '');

                if ($existentes->has($color->id.'|'.($talle ?? ''))) {
                    $omitidas[] = $descripcion;

                    continue;
                }

                $variante = $producto->variantes()->create([
                    'color_id' => $color->id,
                    'talle' => $talle,
                    'sku' => $this->codigos->sku($producto, $color, $talle),
                    'codigo_barras' => $this->codigos->codigoBarras(),
                    'costo' => $costo,
                    'precio_final' => $precioFinal,
                    'activo' => true,
                ]);

                $creadas[] = $variante->setRelation('color', $color);
            }
        }

        return ['creadas' => $creadas, 'omitidas' => $omitidas];
    }
}
