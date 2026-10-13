<?php

namespace App\Console\Commands;

use App\Enums\TipoMovimientoCuenta;
use App\Models\CuentaCorriente;
use App\Services\Importes;
use App\Services\RegistradorAuditoria;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Verifica que el saldo guardado de cada cuenta corriente coincida con Σ debe − Σ haber de
 * sus movimientos (la fuente de verdad) y, con --corregir, lo corrige y lo audita.
 */
class RecalcularCuentasCorrientes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cuentas-corrientes:recalcular {--corregir : Corrige y audita las diferencias encontradas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compara el saldo guardado de las cuentas corrientes con sus movimientos';

    /**
     * Devuelve error si quedan diferencias sin corregir, para poder usarlo en controles automáticos.
     */
    public function handle(RegistradorAuditoria $auditoria): int
    {
        $diferencias = $this->diferencias();

        if ($diferencias->isEmpty()) {
            $this->components->info('Los saldos de las cuentas corrientes coinciden con sus movimientos.');

            return self::SUCCESS;
        }

        $this->table(
            ['Cuenta', 'Cliente', 'Guardado', 'Movimientos'],
            $diferencias->map(fn (object $fila) => [$fila->id, $fila->cliente_id, $fila->saldo, $fila->calculado])->all(),
        );

        if (! $this->option('corregir')) {
            $this->components->warn("Hay {$diferencias->count()} diferencias. Ejecutá con --corregir para corregirlas.");

            return self::FAILURE;
        }

        DB::transaction(function () use ($diferencias, $auditoria) {
            foreach ($diferencias as $fila) {
                $cuenta = CuentaCorriente::query()->lockForUpdate()->findOrFail($fila->id);
                $anterior = (string) $cuenta->saldo;

                $cuenta->forceFill(['saldo' => $fila->calculado])->save();

                $auditoria->registrar('cuenta_corriente.recalculada', $cuenta, ['saldo' => $anterior], ['saldo' => $fila->calculado]);
            }
        });

        $this->components->info("Se corrigieron {$diferencias->count()} diferencias.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, object{id: int, cliente_id: int, saldo: string, calculado: string}>
     */
    private function diferencias(): Collection
    {
        $calculado = DB::table('cc_movimientos as m')
            ->selectRaw('COALESCE(SUM(CASE WHEN m.tipo = ? THEN m.importe ELSE -m.importe END), 0)', [TipoMovimientoCuenta::Debe->value])
            ->whereColumn('m.cuenta_id', 'cuentas_corrientes.id');

        return DB::table('cuentas_corrientes')
            ->select('id', 'cliente_id', 'saldo')
            ->selectSub($calculado, 'saldo_movimientos')
            ->orderBy('id')
            ->get()
            ->map(function (object $fila) {
                $fila->saldo = Importes::normalizar((string) $fila->saldo);
                $fila->calculado = Importes::normalizar((string) $fila->saldo_movimientos);

                return $fila;
            })
            ->filter(fn (object $fila) => $fila->saldo !== $fila->calculado)
            ->values();
    }
}
