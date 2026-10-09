<?php

namespace App\Services;

use App\Http\Middleware\IdentificarDispositivo;
use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Registra acciones sensibles en la auditoría (USR-03).
 *
 * Se debe llamar dentro de la misma transacción que la operación auditada, para que
 * si una falla no quede ninguna de las dos.
 */
class RegistradorAuditoria
{
    /**
     * Campos que nunca se guardan con su valor; si cambiaron, solo se deja constancia.
     */
    private const CAMPOS_SENSIBLES = ['password', 'remember_token', 'token'];

    public const VALOR_OCULTO = '(modificado)';

    public function __construct(
        private Request $request,
        private SucursalActiva $sucursalActiva,
    ) {}

    /**
     * @param  array<string, mixed>|null  $antes  Valores anteriores de los campos que cambiaron.
     * @param  array<string, mixed>|null  $despues  Valores nuevos de los campos que cambiaron.
     */
    public function registrar(string $accion, ?Model $modelo = null, ?array $antes = null, ?array $despues = null): Auditoria
    {
        [$antes, $despues] = $this->ocultarSensibles($antes, $despues);

        return Auditoria::create([
            'user_id' => Auth::id(),
            'sucursal_id' => $this->sucursalActiva->id(),
            'accion' => $accion,
            'modelo' => $modelo?->getMorphClass(),
            'modelo_id' => $modelo?->getKey(),
            'antes' => $antes,
            'despues' => $despues,
            'ip' => $this->request->ip(),
            'dispositivo' => $this->request->attributes->get(IdentificarDispositivo::ATRIBUTO),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 255, ''),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $despues
     * @return array{0: array<string, mixed>|null, 1: array<string, mixed>|null}
     */
    private function ocultarSensibles(?array $antes, ?array $despues): array
    {
        foreach (self::CAMPOS_SENSIBLES as $campo) {
            $cambio = ($antes !== null && array_key_exists($campo, $antes))
                || ($despues !== null && array_key_exists($campo, $despues));

            if (! $cambio) {
                continue;
            }

            if ($antes !== null) {
                unset($antes[$campo]);
            }

            $despues ??= [];
            $despues[$campo] = self::VALOR_OCULTO;
        }

        return [$antes, $despues];
    }
}
