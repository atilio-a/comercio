<?php

namespace Tests\Feature\Catalogo;

use App\Models\AlicuotaIva;
use App\Models\Categoria;
use App\Models\Color;
use Database\Seeders\CategoriasCalzadoSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatosInicialesCatalogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_data_creates_vat_rates_and_the_default_color_without_categories(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['21.00', '10.50', '27.00', '0.00'],
            AlicuotaIva::activos()->pluck('porcentaje')->all(),
        );
        $this->assertSame(['Único'], Color::activos()->pluck('nombre')->all());
        $this->assertSame(0, Categoria::count());
    }

    public function test_initial_data_can_be_seeded_twice_without_duplicates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, AlicuotaIva::count());
        $this->assertSame(1, Color::count());
    }

    public function test_footwear_categories_are_an_optional_example(): void
    {
        $this->seed(CategoriasCalzadoSeeder::class);
        $this->seed(CategoriasCalzadoSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['Botas', 'Sandalias', 'Zapatos', 'Zapatillas'],
            Categoria::pluck('nombre')->all(),
        );
    }
}
