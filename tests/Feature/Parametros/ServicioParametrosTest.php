<?php

namespace Tests\Feature\Parametros;

use App\Enums\Parametro;
use App\Models\Parametro as ParametroGuardado;
use App\Services\Parametros;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ServicioParametrosTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_parameters_return_their_default_value(): void
    {
        $parametros = app(Parametros::class);

        $this->assertTrue($parametros->habilitado(Parametro::UsarTalles));
        $this->assertFalse($parametros->habilitado(Parametro::MultiSucursal));
        $this->assertFalse($parametros->habilitado(Parametro::StockNegativo));
        $this->assertFalse($parametros->habilitado(Parametro::FacturacionElectronica));
    }

    public function test_stored_values_override_defaults(): void
    {
        ParametroGuardado::create(['clave' => Parametro::UsarTalles->value, 'valor' => '0']);

        $this->assertFalse(app(Parametros::class)->habilitado(Parametro::UsarTalles));
    }

    public function test_saving_returns_the_previous_value_and_refreshes_the_cache(): void
    {
        $parametros = app(Parametros::class);
        $this->assertFalse($parametros->habilitado(Parametro::StockNegativo));

        $anterior = $parametros->guardar(Parametro::StockNegativo, true);

        $this->assertFalse($anterior);
        $this->assertTrue($parametros->habilitado(Parametro::StockNegativo));
        $this->assertSame('1', ParametroGuardado::where('clave', 'stock_negativo')->value('valor'));
    }

    public function test_values_are_cached_between_reads(): void
    {
        $parametros = app(Parametros::class);
        $parametros->habilitado(Parametro::MultiSucursal);

        DB::enableQueryLog();
        $parametros->todos();

        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_cache_does_not_keep_values_from_a_rolled_back_transaction(): void
    {
        $parametros = app(Parametros::class);

        try {
            DB::transaction(function () use ($parametros) {
                $parametros->guardar(Parametro::MultiSucursal, true);
                $this->assertTrue($parametros->habilitado(Parametro::MultiSucursal));

                throw new RuntimeException('falla simulada');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertFalse($parametros->habilitado(Parametro::MultiSucursal));
    }

    public function test_seeder_creates_defaults_and_keeps_values_already_changed(): void
    {
        $this->seed(ParametroSeeder::class);
        app(Parametros::class)->guardar(Parametro::UsarTalles, false);

        $this->seed(ParametroSeeder::class);

        $this->assertSame(count(Parametro::cases()), ParametroGuardado::count());
        $this->assertFalse(app(Parametros::class)->habilitado(Parametro::UsarTalles));
        $this->assertFalse(app(Parametros::class)->habilitado(Parametro::MultiSucursal));
    }
}
