<?php

namespace App\Http\Requests\Catalogo\Concerns;

use App\Enums\Parametro;
use App\Models\Color;
use App\Services\GeneradorVariantes;
use App\Services\Parametros;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

/**
 * Reglas compartidas por el alta de producto y el agregado de variantes: colores, talles
 * (según el parámetro "Usar talles en productos"), costo y precio final.
 */
trait ValidaGrillaVariantes
{
    /**
     * @return array<string, mixed>
     */
    protected function reglasGrilla(): array
    {
        return [
            'colores' => ['required', 'array', 'min:1'],
            'colores.*' => ['integer', 'distinct', Rule::exists('colores', 'id')->where('activo', true)],
            'talles' => $this->usaTalles() ? ['required', 'string', 'max:500'] : ['prohibited'],
            ...$this->reglasImportes(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function reglasImportes(): array
    {
        return [
            'costo' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'precio_final' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999.99'],
        ];
    }

    /**
     * Valida el formato de los talles y el tamaño máximo de la grilla.
     */
    protected function validarGrilla(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['colores', 'colores.*', 'talles'])) {
            return;
        }

        try {
            $talles = $this->talles();
        } catch (InvalidArgumentException $error) {
            $validator->errors()->add('talles', $error->getMessage());

            return;
        }

        if ($talles === []) {
            $validator->errors()->add('talles', 'Ingresá al menos un talle.');

            return;
        }

        $cantidad = GeneradorVariantes::cantidad(count((array) $this->input('colores')), $talles);

        if ($cantidad > GeneradorVariantes::MAXIMO_COMBINACIONES) {
            $validator->errors()->add('colores', "La grilla genera {$cantidad} variantes; el máximo es ".GeneradorVariantes::MAXIMO_COMBINACIONES.'.');
        }
    }

    /**
     * @return array<string, string>
     */
    protected function mensajesGrilla(): array
    {
        return [
            'colores.required' => 'Elegí al menos un color: el producto debe tener al menos una variante.',
            'colores.min' => 'Elegí al menos un color: el producto debe tener al menos una variante.',
            'talles.prohibited' => 'Los talles están deshabilitados en los parámetros del sistema.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function atributosGrilla(): array
    {
        return [
            'colores' => 'colores',
            'colores.*' => 'color',
            'talles' => 'talles',
            'costo' => 'costo',
            'precio_final' => 'precio final',
        ];
    }

    /**
     * Talles interpretados, o null si los talles están deshabilitados.
     *
     * @return list<string>|null
     */
    public function talles(): ?array
    {
        return $this->usaTalles() ? GeneradorVariantes::parsearTalles((string) $this->input('talles')) : null;
    }

    /**
     * @return Collection<int, Color>
     */
    public function colores(): Collection
    {
        return Color::query()->whereKey($this->validated('colores'))->orderBy('nombre')->get();
    }

    protected function usaTalles(): bool
    {
        return app(Parametros::class)->habilitado(Parametro::UsarTalles);
    }
}
