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
use App\Services\Parametros;
use App\Services\SucursalActiva;
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
