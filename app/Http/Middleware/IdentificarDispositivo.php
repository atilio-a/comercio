<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asigna a cada navegador un identificador persistente (RNF-09) y lo deja disponible
 * en la solicitud para la auditoría y las operaciones de dinero.
 */
class IdentificarDispositivo
{
    public const COOKIE = 'dispositivo_id';

    public const ATRIBUTO = 'dispositivo_id';

    /**
     * Cinco años, en minutos.
     */
    private const DURACION_MINUTOS = 60 * 24 * 365 * 5;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $dispositivo = $request->cookie(self::COOKIE);
        $esNuevo = ! is_string($dispositivo) || ! Str::isUuid($dispositivo);

        if ($esNuevo) {
            $dispositivo = (string) Str::uuid();
        }

        $request->attributes->set(self::ATRIBUTO, $dispositivo);

        $response = $next($request);

        if ($esNuevo) {
            $response->headers->setCookie(Cookie::make(
                name: self::COOKIE,
                value: $dispositivo,
                minutes: self::DURACION_MINUTOS,
                secure: null,
                httpOnly: true,
                sameSite: 'lax',
            ));
        }

        return $response;
    }
}
