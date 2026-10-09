<?php

namespace App\Services;

use App\Enums\EstadoSesionCaja;
use App\Enums\TipoMovimientoCaja;
use App\Exceptions\CajaNoAbierta;
use App\Http\Middleware\IdentificarDispositivo;
use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Models\MedioPago;
use App\Models\Sucursal;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Única puerta para registrar dinero en una caja (CAJ-03, CAJ-05).
 *
 * Contrato para los módulos que la usan (ventas, cobranzas, compras, devoluciones):
 * - Llamar siempre dentro de una transacción abierta por quien llama, que define qué más
 *   se graba junto con el movimiento (venta, stock, cuenta corriente…). Fuera de una
 *   transacción lanza LogicException.
 * - El movimiento va a la sesión indicada o, si no se indica, a la sesión abierta del
 *   usuario autenticado en la sucursal activa. Sin sesión abierta, o con la sesión ya
 *   cerrada, lanza {@see CajaNoAbierta}.
 * - Cada llamada bloquea la fila de la sesión (`lockForUpdate`) hasta el final de la
 *   transacción, así el cierre de caja no puede quedar a mitad de un cobro. Si la
 *   operación también mueve stock, conviene bloquear la sesión de caja después del stock
 *   (orden: stock por `variante_id` ascendente, luego caja) para evitar deadlocks.
 * - El importe es un decimal en string con hasta dos decimales y con signo: positivo
 *   entra a la caja, negativo sale. El signo debe coincidir con el del tipo
 *   ({@see TipoMovimientoCaja::signo()}); ingresos y egresos varios exigen motivo; el
 *   medio debe estar activo y, si requiere referencia, la referencia es obligatoria.
 *   Las violaciones lanzan InvalidArgumentException.
 * - Los movimientos son inmutables: para corregir uno se usa {@see contraasentar()}, que
 *   registra el importe opuesto en la misma sesión (que debe seguir abierta) y lo audita.
 */
class MovimientosCaja
{
    public function __construct(
        private SucursalActiva $sucursalActiva,
        private RegistradorAuditoria $auditoria,
        private Request $request,
    ) {}

    /**
     * Registra un movimiento de dinero.
     *
     * @param  string  $importe  Con signo, por ejemplo "2500.00" o "-1200.50".
     * @param  CarbonInterface|null  $fecha  Fecha de negocio; hoy si es null.
     *
     * @throws CajaNoAbierta
     * @throws InvalidArgumentException
     */
    public function registrar(
        TipoMovimientoCaja $tipo,
        MedioPago $medio,
        string $importe,
        ?string $motivo = null,
        ?Model $origen = null,
        ?CajaSesion $sesion = null,
        ?string $referencia = null,
        ?CarbonInterface $fecha = null,
    ): CajaMovimiento {
        $this->exigirTransaccion();

        $centavos = $this->centavos($importe);

        if ($tipo->signo() !== ($centavos <=> 0)) {
            throw new InvalidArgumentException("El importe de un movimiento de {$tipo->etiqueta()} debe ser ".($tipo->signo() > 0 ? 'positivo.' : 'negativo.'));
        }

        $motivo = $this->limpiar($motivo);
        $referencia = $this->limpiar($referencia);

        if ($tipo->exigeMotivo() && $motivo === null) {
            throw new InvalidArgumentException('Indicá el motivo del movimiento.');
        }

        if (! $medio->activo) {
            throw new InvalidArgumentException("El medio de pago {$medio->nombre} está inactivo.");
        }

        if ($medio->requiere_referencia && $referencia === null) {
            throw new InvalidArgumentException("El medio de pago {$medio->nombre} requiere una referencia.");
        }

        $sesion = $this->bloquear($sesion ?? $this->sesionAbierta());

        return $this->crear($sesion, $tipo, $medio, $centavos, $motivo, $referencia, $origen, $fecha);
    }

    /**
     * Revierte un movimiento con otro del mismo tipo y medio, con el importe opuesto,
     * en la misma sesión. Solo se puede revertir una vez y nunca un contraasiento.
     *
     * @throws CajaNoAbierta Si la sesión del movimiento ya está cerrada.
     * @throws InvalidArgumentException
     */
    public function contraasentar(CajaMovimiento $original, string $motivo): CajaMovimiento
    {
        $this->exigirTransaccion();

        $motivo = $this->limpiar($motivo);

        if ($motivo === null) {
            throw new InvalidArgumentException('Indicá el motivo del contraasiento.');
        }

        if ($original->esContraasiento()) {
            throw new InvalidArgumentException('Un contraasiento no se puede revertir.');
        }

        $sesion = $this->bloquear($original->sesion);

        if (CajaMovimiento::query()->where('reversa_de_id', $original->id)->exists()) {
            throw new InvalidArgumentException('El movimiento ya fue revertido.');
        }

        $contraasiento = $this->crear(
            $sesion,
            $original->tipo,
            $original->medioPago,
            -$this->centavos((string) $original->importe),
            $motivo,
            $original->referencia,
            null,
            null,
            $original,
        );

        $this->auditoria->registrar('caja.contraasiento', $contraasiento, [
            'movimiento_id' => $original->id,
            'importe' => (string) $original->importe,
        ], [
            'importe' => (string) $contraasiento->importe,
            'tipo' => $original->tipo->value,
            'medio_pago_id' => $original->medio_pago_id,
            'caja_sesion_id' => $sesion->id,
            'motivo' => $motivo,
        ]);

        return $contraasiento;
    }

    /**
     * Sesión abierta del usuario (el autenticado si es null) en la sucursal (la activa si es null).
     */
    public function sesionAbierta(?User $user = null, ?Sucursal $sucursal = null): ?CajaSesion
    {
        $userId = $user?->id ?? Auth::id();
        $sucursalId = $sucursal?->id ?? $this->sucursalActiva->id();

        if ($userId === null || $sucursalId === null) {
            return null;
        }

        return CajaSesion::query()
            ->where('user_id', $userId)
            ->where('sucursal_id', $sucursalId)
            ->where('estado', EstadoSesionCaja::Abierta->value)
            ->first();
    }

    private function crear(
        CajaSesion $sesion,
        TipoMovimientoCaja $tipo,
        MedioPago $medio,
        int $centavos,
        ?string $motivo,
        ?string $referencia,
        ?Model $origen,
        ?CarbonInterface $fecha,
        ?CajaMovimiento $reversaDe = null,
    ): CajaMovimiento {
        return CajaMovimiento::create([
            'caja_sesion_id' => $sesion->id,
            'sucursal_id' => $sesion->sucursal_id,
            'fecha' => ($fecha ?? now())->toDateString(),
            'tipo' => $tipo,
            'medio_pago_id' => $medio->id,
            'importe' => Importes::aDecimal($centavos),
            'motivo' => $motivo,
            'referencia' => $referencia,
            'origen_type' => $origen?->getMorphClass(),
            'origen_id' => $origen?->getKey(),
            'reversa_de_id' => $reversaDe?->id,
            'user_id' => Auth::id(),
            'dispositivo' => $this->request->attributes->get(IdentificarDispositivo::ATRIBUTO),
        ]);
    }

    /**
     * Bloquea la sesión hasta el final de la transacción y verifica que siga abierta.
     *
     * @throws CajaNoAbierta
     */
    private function bloquear(?CajaSesion $sesion): CajaSesion
    {
        if (! $sesion) {
            throw new CajaNoAbierta;
        }

        $bloqueada = CajaSesion::query()->lockForUpdate()->findOrFail($sesion->id);

        if (! $bloqueada->estaAbierta()) {
            throw new CajaNoAbierta('La sesión de caja está cerrada.');
        }

        return $bloqueada;
    }

    private function centavos(string $importe): int
    {
        if (! Importes::esValido($importe)) {
            throw new InvalidArgumentException("Importe inválido: {$importe}");
        }

        $centavos = Importes::aCentavos($importe);

        if ($centavos === 0) {
            throw new InvalidArgumentException('Un movimiento de caja no puede tener importe 0.');
        }

        return $centavos;
    }

    private function limpiar(?string $texto): ?string
    {
        $texto = trim((string) $texto);

        return $texto === '' ? null : $texto;
    }

    private function exigirTransaccion(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Los movimientos de caja se registran dentro de una transacción.');
        }
    }
}
