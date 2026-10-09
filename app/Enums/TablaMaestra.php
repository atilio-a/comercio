<?php

namespace App\Enums;

use App\Models\Categoria;
use App\Models\Color;
use App\Models\Marca;
use App\Models\Temporada;
use Illuminate\Database\Eloquent\Model;

/**
 * Tablas maestras simples del catálogo (nombre + activo), administradas con un único controlador.
 * El valor es el segmento de la URL y el nombre de la tabla.
 */
enum TablaMaestra: string
{
    case Categorias = 'categorias';
    case Marcas = 'marcas';
    case Temporadas = 'temporadas';
    case Colores = 'colores';

    /**
     * @return class-string<Model>
     */
    public function modelo(): string
    {
        return match ($this) {
            self::Categorias => Categoria::class,
            self::Marcas => Marca::class,
            self::Temporadas => Temporada::class,
            self::Colores => Color::class,
        };
    }

    public function singular(): string
    {
        return match ($this) {
            self::Categorias => 'categoría',
            self::Marcas => 'marca',
            self::Temporadas => 'temporada',
            self::Colores => 'color',
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::Categorias => 'Categorías',
            self::Marcas => 'Marcas',
            self::Temporadas => 'Temporadas',
            self::Colores => 'Colores',
        };
    }

    /**
     * Título del alta, con el género gramatical correcto ("Nueva marca", "Nuevo color").
     */
    public function tituloAlta(): string
    {
        return ($this === self::Colores ? 'Nuevo ' : 'Nueva ').$this->singular();
    }
}
