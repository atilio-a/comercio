<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Services\RegistradorAuditoria;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Verifica que la cantidad guardada de cada stock coincida con el saldo de su último
 * movimiento (los movimientos son la fuente de verdad) y, con --corregir, la corrige.
 */
class RecalcularStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:recalcular {--corregir : Corrige y audita las diferencias encontradas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compara el stock guardado con el saldo del kardex';

    /**
     * Devuelve error si quedan diferencias sin corregir, para poder usarlo en controles automáticos.
     */
    public function handle(RegistradorAuditoria $auditoria): int
    {
        $diferencias = $this->diferencias();

        if ($diferencias->isEmpty()) {
            $this->components->info('El stock coincide con el kardex.');

            return self::SUCCESS;
        }

        $this->table(
            ['Stock', 'Variante', 'Sucursal', 'Guardado', 'Kardex'],
            $diferencias->map(fn (object $fila) => [$fila->id, $fila->variante_id, $fila->sucursal_id, $fila->cantidad, $fila->saldo])->all(),
        );

        if (! $this->option('corregir')) {
            $this->components->warn("Hay {$diferencias->count()} diferencias. Ejecutá con --corregir para corregirlas.");

            return self::FAILURE;
        }

        DB::transaction(function () use ($diferencias, $auditoria) {
            foreach ($diferencias->sortBy('variante_id') as $fila) {
                $stock = Stock::query()->lockForUpdate()->findOrFail($fila->id);
                $anterior = $stock->cantidad;

                $stock->forceFill(['cantidad' => (int) $fila->saldo])->save();

                $auditoria->registrar('stock.recalculado', $stock, ['cantidad' => $anterior], ['cantidad' => (int) $fila->saldo]);
            }
        });

        $this->components->info("Se corrigieron {$diferencias->count()} diferencias.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, object{id: int, variante_id: int, sucursal_id: int, cantidad: int, saldo: int}>
     */
    private function diferencias()
    {
        $ultimoSaldo = DB::table('movimientos_stock as m')
            ->select('m.saldo')
            ->whereColumn('m.variante_id', 'stock.variante_id')
            ->whereColumn('m.sucursal_id', 'stock.sucursal_id')
            ->orderByDesc('m.id')
            ->limit(1);

        return DB::table('stock')
            ->select('stock.id', 'stock.variante_id', 'stock.sucursal_id', 'stock.cantidad')
            ->selectSub($ultimoSaldo, 'saldo_kardex')
            ->orderBy('stock.id')
            ->get()
            ->map(function (object $fila) {
                $fila->saldo = (int) ($fila->saldo_kardex ?? 0);

                return $fila;
            })
            ->filter(fn (object $fila) => (int) $fila->cantidad !== $fila->saldo)
            ->values();
    }
}
