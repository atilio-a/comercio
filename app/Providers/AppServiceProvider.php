<?php

namespace App\Providers;

use App\Enums\Parametro;
use App\Enums\Rol;
use App\Models\AlicuotaIva;
use App\Models\Categoria;
use App\Models\Color;
use App\Models\Marca;
use App\Models\Temporada;
use App\Models\User;
use App\Policies\MaestroPolicy;
use App\Services\Comprobantes\EmisorComprobante;
use App\Services\Comprobantes\EmisorInterno;
use App\Services\Comprobantes\EmisorNoDisponible;
use App\Services\Parametros;
use App\Services\SucursalActiva;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(SucursalActiva::class);
        $this->app->singleton(Parametros::class);

        // Issuer chosen per resolution: the internal one until electronic invoicing (ARCA) exists.
        $this->app->bind(EmisorComprobante::class, fn (Application $app): EmisorComprobante => $app->make(Parametros::class)->habilitado(Parametro::FacturacionElectronica)
            ? $app->make(EmisorNoDisponible::class)
            : $app->make(EmisorInterno::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The Administrador role has every permission in its active branch, including future ones.
        Gate::before(function (User $user): ?bool {
            return $user->hasRole(Rol::Administrador->value) ? true : null;
        });

        foreach ([Categoria::class, Marca::class, Temporada::class, Color::class, AlicuotaIva::class] as $maestro) {
            Gate::policy($maestro, MaestroPolicy::class);
        }

        View::composer('layouts.navigation', function (\Illuminate\View\View $view) {
            $view->with([
                'multiSucursal' => app(Parametros::class)->habilitado(Parametro::MultiSucursal),
                'sucursalActiva' => app(SucursalActiva::class)->sucursal(),
            ]);
        });
    }
}
