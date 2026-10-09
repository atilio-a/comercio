<?php

namespace Tests\Feature\Catalogo;

use App\Models\AlicuotaIva;
use App\Models\Categoria;
use App\Models\Color;
use App\Models\Marca;
use App\Models\Temporada;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TablasMaestrasModeloTest extends TestCase
{
    use RefreshDatabase;

    public function test_names_are_unique_regardless_of_case(): void
    {
        foreach ([Categoria::class, Marca::class, Temporada::class, Color::class] as $modelo) {
            $modelo::create(['nombre' => 'Botas']);

            try {
                $modelo::create(['nombre' => 'botas']);
                $this->fail("{$modelo} aceptó un nombre repetido con otra capitalización.");
            } catch (UniqueConstraintViolationException) {
                $this->assertSame(1, $modelo::count());
            }
        }
    }

    public function test_active_scope_excludes_disabled_records(): void
    {
        $activa = Categoria::factory()->create();
        $inactiva = Categoria::factory()->inactivo()->create();

        $this->assertSame([$activa->id], Categoria::activos()->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$activa->id, $inactiva->id],
            Categoria::activosOIncluido($inactiva->id)->pluck('id')->all(),
        );
    }

    public function test_vat_rates_keep_two_decimals(): void
    {
        $alicuota = AlicuotaIva::create(['nombre' => 'IVA 10,5 %', 'porcentaje' => '10.5']);

        $this->assertSame('10.50', $alicuota->fresh()->porcentaje);
        $this->assertTrue($alicuota->fresh()->activo);
    }
}
