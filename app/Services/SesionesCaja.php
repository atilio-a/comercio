<?php

namespace App\Services;

use App\Enums\EstadoSesionCaja;
use App\Enums\TipoMovimientoCaja;
use App\Exceptions\CajaNoAbierta;
use App\Exceptions\CajaYaAbierta;
use App\Http\Middleware\IdentificarDispositivo;
use App\Models\Caja;
use App\Models\CajaSesion;
use App\Models\CajaSesionArqueo;
use App\Models\MedioPago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Apertura, arqueo y cierre de caja por usuario (CAJ-02).
 *
 * Cada operación abre su propia transacción (o se suma a la de quien llama). Una caja
 * tiene como máximo una sesión abierta y un usuario como máximo una por sucursal; la
 * base lo garantiza además con índices únicos sobre columnas generadas.
 */
class SesionesCaja
{
    public function __construct(
        private MovimientosCaja $movimientos,
        private SucursalActiva $sucursalActiva,
        private RegistradorAuditoria $auditoria,
        private Request $request,
    ) {}

    /**
     * Abre la caja para el usuario autenticado. Un fondo mayor a 0 se registra como
     * movimiento de apertura en efectivo.
     *
     * @param  string  $fondo  Decimal mayor o igual a 0, por ejemplo "5000.00".
     *
     * @throws CajaYaAbierta
     * @throws InvalidArgumentException
     */
    public function abrir(Caja $caja, string $fondo): CajaSesion
    {
        if (! Importes::esValido($fondo) || Importes::aCentavos($fondo) < 0) {
            throw new InvalidArgumentException('El fondo inicial debe ser un importe mayor o igual a 0.');
        }

        $fondo = Importes::normalizar($fondo);

        return DB::transaction(function () use ($caja, $fondo) {
            $caja = Caja::query()->lockForUpdate()->findOrFail($caja->id);

            if (! $caja->activa || (int) $caja->sucursal_id !== (int) $this->sucursalActiva->id()) {
                throw new InvalidArgumentException('La caja no está disponible en esta sucursal.');
            }

            if ($caja->sesionAbierta()->exists()) {
                throw new CajaYaAbierta("La caja {$caja->nombre} ya está abierta.");
            }

            if ($this->movimientos->sesionAbierta() !== null) {
                throw new CajaYaAbierta('Ya tenés otra caja abierta en esta sucursal.');
            }

            $efectivo = MedioPago::efectivo();

            if (Importes::aCentavos($fondo) > 0 && ! $efectivo) {
                throw new InvalidArgumentException('No hay un medio de pago de efectivo activo para registrar el fondo inicial.');
            }

            $sesion = CajaSesion::create([
                'caja_id' => $caja->id,
                'sucursal_id' => $caja->sucursal_id,
                'user_id' => Auth::id(),
                'fecha' => now()->toDateString(),
                'apertura' => now(),
                'fondo_inicial' => $fondo,
                'estado' => EstadoSesionCaja::Abierta,
                'dispositivo' => $this->request->attributes->get(IdentificarDispositivo::ATRIBUTO),
            ]);

            if (Importes::aCentavos($fondo) > 0) {
                $this->movimientos->registrar(TipoMovimientoCaja::Apertura, $efectivo, $fondo, 'Fondo inicial', sesion: $sesion);
            }

            $this->auditoria->registrar('caja.apertura', $sesion, null, [
                'caja_id' => $caja->id,
                'fondo_inicial' => $fondo,
            ]);

            return $sesion;
        });
    }

    /**
     * Medios a arquear al cerrar: los que tienen movimientos en la sesión y los activos
     * que impactan en caja. Solo se cuentan los que impactan en caja.
     *
     * @return list<array{medio: MedioPago, esperado: string, cuenta: bool}>
     */
    public function arqueoPrevisto(CajaSesion $sesion): array
    {
        $esperado = $sesion->esperadoPorMedio();

        return MedioPago::query()
            ->where(fn ($query) => $query
                ->whereIn('id', array_keys($esperado))
                ->orWhere(fn ($query) => $query->where('activo', true)->where('impacta_caja', true)))
            ->orderByDesc('impacta_caja')
            ->orderBy('id')
            ->get()
            ->map(fn (MedioPago $medio) => [
                'medio' => $medio,
                'esperado' => $esperado[$medio->id] ?? '0.00',
                'cuenta' => $medio->impacta_caja,
            ])
            ->values()
            ->all();
    }

    /**
     * Cierra la sesión con el arqueo. Guarda esperado, contado y diferencia por medio, y
     * exige justificación si alguna diferencia es distinta de 0.
     *
     * @param  array<int, string>  $contado  Importe contado por id de medio de pago.
     *
     * @throws CajaNoAbierta
     * @throws InvalidArgumentException
     */
    public function cerrar(CajaSesion $sesion, array $contado, ?string $justificacion): CajaSesion
    {
        $justificacion = trim((string) $justificacion);
        $justificacion = $justificacion === '' ? null : $justificacion;

        return DB::transaction(function () use ($sesion, $contado, $justificacion) {
            $sesion = CajaSesion::query()->lockForUpdate()->findOrFail($sesion->id);

            if (! $sesion->estaAbierta()) {
                throw new CajaNoAbierta('La sesión de caja ya está cerrada.');
            }

            if ((int) $sesion->user_id !== (int) Auth::id()) {
                throw new InvalidArgumentException('Solo quien abrió la caja la puede cerrar.');
            }

            $filas = [];
            $diferencias = [];

            foreach ($this->arqueoPrevisto($sesion) as ['medio' => $medio, 'esperado' => $esperado, 'cuenta' => $cuenta]) {
                $fila = ['medio_pago_id' => $medio->id, 'esperado' => $esperado, 'contado' => null, 'diferencia' => null];

                if ($cuenta) {
                    $valor = (string) ($contado[$medio->id] ?? '');

                    if (! Importes::esValido($valor) || Importes::aCentavos($valor) < 0) {
                        throw new InvalidArgumentException("Indicá lo contado en {$medio->nombre}.");
                    }

                    $diferencia = Importes::aCentavos($valor) - Importes::aCentavos($esperado);
                    $fila['contado'] = Importes::normalizar($valor);
                    $fila['diferencia'] = Importes::aDecimal($diferencia);

                    if ($diferencia !== 0) {
                        $diferencias[$medio->id] = $fila['diferencia'];
                    }
                }

                $filas[] = $fila;
            }

            if ($diferencias !== [] && $justificacion === null) {
                throw new InvalidArgumentException('Justificá la diferencia de caja.');
            }

            foreach ($filas as $fila) {
                CajaSesionArqueo::create(['caja_sesion_id' => $sesion->id] + $fila);
            }

            $sesion->forceFill([
                'estado' => EstadoSesionCaja::Cerrada,
                'cierre' => now(),
                'justificacion' => $justificacion,
            ])->save();

            $this->auditoria->registrar('caja.cierre', $sesion, ['estado' => EstadoSesionCaja::Abierta->value], [
                'estado' => EstadoSesionCaja::Cerrada->value,
                'arqueo' => $filas,
            ]);

            if ($diferencias !== []) {
                $this->auditoria->registrar('caja.diferencia', $sesion, null, [
                    'diferencias' => $diferencias,
                    'justificacion' => $justificacion,
                ]);
            }

            return $sesion;
        });
    }
}
