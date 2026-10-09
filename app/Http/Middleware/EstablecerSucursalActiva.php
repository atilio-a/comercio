<?php

namespace App\Http\Middleware;

use App\Enums\Parametro;
use App\Models\Sucursal;
use App\Services\Parametros;
use App\Services\SucursalActiva;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Para cada solicitud de un usuario autenticado: verifica que siga activo, que tenga
 * sucursales habilitadas y fija la sucursal activa (y con ella los permisos de spatie).
 */
class EstablecerSucursalActiva
{
    public function __construct(
        private SucursalActiva $sucursalActiva,
        private Parametros $parametros,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! $user->activo) {
            return $this->cerrarSesion($request, __('auth.inactive'));
        }

        $asignadas = $user->sucursales()->activas()->orderBy('sucursales.id')->get();

        if ($asignadas->isEmpty()) {
            return $this->cerrarSesion($request, __('auth.no_branches'));
        }

        /** @var Sucursal|null $sucursal */
        $sucursal = $asignadas->firstWhere('id', $request->session()->get(SucursalActiva::CLAVE_SESION));

        if (! $sucursal && ($asignadas->count() === 1 || ! $this->parametros->habilitado(Parametro::MultiSucursal))) {
            $sucursal = $asignadas->first();
            $request->session()->put(SucursalActiva::CLAVE_SESION, $sucursal->id);
        }

        if (! $sucursal) {
            $this->sucursalActiva->olvidar();

            if (! $request->routeIs('sucursal-activa.*', 'logout')) {
                return redirect()->route('sucursal-activa.edit');
            }

            return $next($request);
        }

        $this->sucursalActiva->establecer($sucursal, $user);

        return $next($request);
    }

    private function cerrarSesion(Request $request, string $motivo): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $motivo]);
    }
}
