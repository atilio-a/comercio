<?php

namespace App\Services;

use App\Enums\TipoMovimientoCuenta;
use App\Exceptions\CreditoNoDisponible;
use App\Http\Middleware\IdentificarDispositivo;
use App\Models\CcMovimiento;
use App\Models\Cliente;
use App\Models\CuentaCorriente;
use App\Models\Sucursal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Única puerta para registrar movimientos en las cuentas corrientes de clientes
 * (VEN-03 saldo a favor; base del crédito de la Fase 2).
 *
 * Convención del saldo: `saldo = Σ debe − Σ haber`. **Positivo = el cliente debe;
 * negativo = el cliente tiene saldo a favor.**
 *
 * Contrato para los módulos que la usan (devoluciones, ventas, cobranzas):
 * - Llamar siempre dentro de una transacción abierta por quien llama, que define qué más se
 *   graba junto con el movimiento (devolución, venta, caja…). Fuera de una transacción lanza
 *   LogicException.
 * - Cada llamada bloquea la fila de la cuenta (`lockForUpdate`) hasta el final de la
 *   transacción. Si la operación también mueve stock o caja, bloquear la cuenta al final
 *   (orden: stock por `variante_id` ascendente, caja, cuenta corriente) para evitar deadlocks.
 * - Si el cliente no tiene cuenta, {@see cuentaDe()} se la crea sin crédito habilitado
 *   (regla del doc 05): admite saldo a favor y usarlo, pero no deuda.
 * - El importe es un decimal en string, positivo, con hasta dos decimales; el tipo (debe o
 *   haber) da el sentido. Importe inválido o concepto vacío lanzan InvalidArgumentException.
 * - Un débito que deja la cuenta con deuda (saldo > 0) lanza {@see CreditoNoDisponible} si
 *   la cuenta no admite crédito, está bloqueada o supera su límite. Un débito que solo consume
 *   saldo a favor siempre se permite.
 * - El movimiento va a la sucursal indicada o a la activa; sin ninguna lanza LogicException.
 * - Los movimientos son inmutables: para corregir uno se usa {@see contraasentar()}, que
 *   registra el tipo opuesto por el mismo importe y lo audita.
 */
class CuentasCorrientes
{
    public function __construct(
        private SucursalActiva $sucursalActiva,
        private RegistradorAuditoria $auditoria,
        private Request $request,
    ) {}

    /**
     * Cuenta del cliente, bloqueada hasta el final de la transacción. Si no existe, la crea
     * sin crédito habilitado, límite 0 y sin bloqueo.
     */
    public function cuentaDe(Cliente $cliente): CuentaCorriente
    {
        $this->exigirTransaccion();

        // firstOrCreate falls back to createOrFirst, so two concurrent calls cannot create two accounts.
        $cuenta = CuentaCorriente::query()->firstOrCreate(['cliente_id' => $cliente->id]);

        return CuentaCorriente::query()->lockForUpdate()->findOrFail($cuenta->id);
    }

    /**
     * Registra un haber: saldo a favor, devolución, pago del cliente.
     *
     * @param  string  $importe  Positivo, por ejemplo "8000.00".
     * @param  CarbonInterface|null  $fecha  Fecha de negocio; hoy si es null.
     *
     * @throws InvalidArgumentException
     */
    public function acreditar(
        Cliente $cliente,
        string $importe,
        string $concepto,
        ?Model $origen = null,
        ?CarbonInterface $fecha = null,
        ?Sucursal $sucursal = null,
    ): CcMovimiento {
        return $this->registrar($cliente, TipoMovimientoCuenta::Haber, $importe, $concepto, $origen, $fecha, null, $sucursal);
    }

    /**
     * Registra un debe: uso del saldo a favor como medio de pago o, con crédito, una venta a cuenta.
     *
     * @param  string  $importe  Positivo, por ejemplo "5000.00".
     * @param  CarbonInterface|null  $fecha  Fecha de negocio; hoy si es null.
     * @param  CarbonInterface|null  $vencimiento  Vencimiento de la deuda, si genera deuda.
     *
     * @throws CreditoNoDisponible
     * @throws InvalidArgumentException
     */
    public function debitar(
        Cliente $cliente,
        string $importe,
        string $concepto,
        ?Model $origen = null,
        ?CarbonInterface $fecha = null,
        ?CarbonInterface $vencimiento = null,
        ?Sucursal $sucursal = null,
    ): CcMovimiento {
        return $this->registrar($cliente, TipoMovimientoCuenta::Debe, $importe, $concepto, $origen, $fecha, $vencimiento, $sucursal);
    }

    /**
     * Revierte un movimiento con otro del tipo opuesto por el mismo importe, en la misma
     * sucursal. Solo se puede revertir una vez y nunca un contraasiento. Revertir un haber es
     * un débito: si el saldo a favor ya se usó en una cuenta sin crédito, se rechaza.
     *
     * @throws CreditoNoDisponible
     * @throws InvalidArgumentException
     */
    public function contraasentar(CcMovimiento $original, string $motivo): CcMovimiento
    {
        $this->exigirTransaccion();

        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new InvalidArgumentException('Indicá el motivo del contraasiento.');
        }

        if ($original->esContraasiento()) {
            throw new InvalidArgumentException('Un contraasiento no se puede revertir.');
        }

        $cuenta = CuentaCorriente::query()->lockForUpdate()->findOrFail($original->cuenta_id);

        if (CcMovimiento::query()->where('reversa_de_id', $original->id)->exists()) {
            throw new InvalidArgumentException('El movimiento ya fue revertido.');
        }

        $contraasiento = $this->crear(
            $cuenta,
            $original->tipo->opuesto(),
            Importes::aCentavos((string) $original->importe),
            $motivo,
            null,
            null,
            null,
            $original->sucursal_id,
            $original,
        );

        $this->auditoria->registrar('cuenta_corriente.contraasiento', $contraasiento, [
            'movimiento_id' => $original->id,
            'tipo' => $original->tipo->value,
            'importe' => (string) $original->importe,
        ], [
            'tipo' => $contraasiento->tipo->value,
            'importe' => (string) $contraasiento->importe,
            'saldo' => (string) $contraasiento->saldo,
            'motivo' => $motivo,
        ]);

        return $contraasiento;
    }

    /**
     * Saldo que resulta de los movimientos de la cuenta (Σ debe − Σ haber), la fuente de verdad.
     */
    public function saldoSegunMovimientos(CuentaCorriente $cuenta): string
    {
        $saldo = CcMovimiento::query()
            ->where('cuenta_id', $cuenta->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN tipo = ? THEN importe ELSE -importe END), 0) as saldo', [TipoMovimientoCuenta::Debe->value])
            ->value('saldo');

        return Importes::normalizar((string) $saldo);
    }

    private function registrar(
        Cliente $cliente,
        TipoMovimientoCuenta $tipo,
        string $importe,
        string $concepto,
        ?Model $origen,
        ?CarbonInterface $fecha,
        ?CarbonInterface $vencimiento,
        ?Sucursal $sucursal,
    ): CcMovimiento {
        $this->exigirTransaccion();

        $centavos = $this->centavos($importe);
        $concepto = trim($concepto);

        if ($concepto === '') {
            throw new InvalidArgumentException('Indicá el concepto del movimiento.');
        }

        $sucursalId = $sucursal?->id ?? $this->sucursalActiva->id()
            ?? throw new LogicException('No hay sucursal para registrar el movimiento de cuenta corriente.');

        return $this->crear($this->cuentaDe($cliente), $tipo, $centavos, $concepto, $origen, $fecha, $vencimiento, $sucursalId);
    }

    /**
     * Crea el movimiento y actualiza el saldo cacheado. La cuenta ya está bloqueada.
     *
     * @throws CreditoNoDisponible
     */
    private function crear(
        CuentaCorriente $cuenta,
        TipoMovimientoCuenta $tipo,
        int $centavos,
        string $concepto,
        ?Model $origen,
        ?CarbonInterface $fecha,
        ?CarbonInterface $vencimiento,
        int $sucursalId,
        ?CcMovimiento $reversaDe = null,
    ): CcMovimiento {
        $saldoAnterior = Importes::aCentavos((string) $cuenta->saldo);
        $saldoNuevo = $saldoAnterior + $tipo->signo() * $centavos;

        if ($tipo === TipoMovimientoCuenta::Debe && $saldoNuevo > 0) {
            $this->verificarCredito($cuenta, $saldoAnterior, $saldoNuevo);
        }

        $movimiento = CcMovimiento::create([
            'cuenta_id' => $cuenta->id,
            'sucursal_id' => $sucursalId,
            'fecha' => ($fecha ?? now())->toDateString(),
            'tipo' => $tipo,
            'importe' => Importes::aDecimal($centavos),
            'saldo' => Importes::aDecimal($saldoNuevo),
            'concepto' => mb_substr($concepto, 0, 255),
            'origen_type' => $origen?->getMorphClass(),
            'origen_id' => $origen?->getKey(),
            'reversa_de_id' => $reversaDe?->id,
            'vencimiento' => $vencimiento?->toDateString(),
            'user_id' => Auth::id(),
            'dispositivo' => $this->request->attributes->get(IdentificarDispositivo::ATRIBUTO),
        ]);

        $cuenta->forceFill(['saldo' => Importes::aDecimal($saldoNuevo)])->save();

        return $movimiento;
    }

    /**
     * Un débito que deja deuda exige crédito habilitado, cuenta sin bloqueo y no superar el límite.
     *
     * @throws CreditoNoDisponible
     */
    private function verificarCredito(CuentaCorriente $cuenta, int $saldoAnterior, int $saldoNuevo): void
    {
        $saldoAFavor = Importes::aDecimal(max(0, -$saldoAnterior));

        if (! $cuenta->admite_credito) {
            throw CreditoNoDisponible::sinCredito($cuenta, $saldoAFavor);
        }

        if ($cuenta->bloqueada) {
            throw CreditoNoDisponible::bloqueada($cuenta, $saldoAFavor);
        }

        $limite = Importes::aCentavos((string) $cuenta->limite_credito);

        if ($saldoNuevo > $limite) {
            throw CreditoNoDisponible::limiteSuperado($cuenta, Importes::aDecimal(max(0, $limite - $saldoAnterior)));
        }
    }

    private function centavos(string $importe): int
    {
        if (! Importes::esValido($importe)) {
            throw new InvalidArgumentException("Importe inválido: {$importe}");
        }

        $centavos = Importes::aCentavos($importe);

        if ($centavos <= 0) {
            throw new InvalidArgumentException('El importe de un movimiento de cuenta corriente debe ser mayor que 0.');
        }

        return $centavos;
    }

    private function exigirTransaccion(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Los movimientos de cuenta corriente se registran dentro de una transacción.');
        }
    }
}
