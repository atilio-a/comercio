<?php

namespace Database\Seeders;

use App\Enums\TipoMedioPago;
use App\Models\MedioPago;
use Illuminate\Database\Seeder;

class MedioPagoSeeder extends Seeder
{
    /**
     * Medios de pago básicos (CAJ-01), sin pisar los cambios del Administrador.
     * La tarjeta de crédito arranca con un plan de 1 cuota sin recargo.
     */
    public function run(): void
    {
        $medios = [
            ['Efectivo', TipoMedioPago::Efectivo, true, 0, false],
            ['Tarjeta de débito', TipoMedioPago::TarjetaDebito, false, 1, true],
            ['Tarjeta de crédito', TipoMedioPago::TarjetaCredito, false, 10, true],
            ['Transferencia', TipoMedioPago::Transferencia, false, 0, true],
            ['QR / billetera', TipoMedioPago::Qr, false, 0, true],
            ['Cuenta corriente', TipoMedioPago::CuentaCorriente, false, 0, false],
            ['Saldo a favor', TipoMedioPago::SaldoAFavor, false, 0, false],
        ];

        foreach ($medios as [$nombre, $tipo, $impactaCaja, $diasAcreditacion, $requiereReferencia]) {
            $medio = MedioPago::firstOrCreate(['nombre' => $nombre], [
                'tipo' => $tipo,
                'impacta_caja' => $impactaCaja,
                'dias_acreditacion' => $diasAcreditacion,
                'requiere_referencia' => $requiereReferencia,
                'activo' => true,
            ]);

            if ($medio->wasRecentlyCreated && $tipo->admitePlanes()) {
                $medio->planes()->create(['cuotas' => 1, 'recargo_pct' => '0.00', 'activo' => true]);
            }
        }
    }
}
