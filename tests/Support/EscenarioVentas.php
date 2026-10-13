<?php

namespace Tests\Support;

use App\Enums\Rol;
use App\Enums\TipoMedioPago;
use App\Http\Middleware\IdentificarDispositivo;
use App\Models\AlicuotaIva;
use App\Models\Caja;
use App\Models\CajaSesion;
use App\Models\Cliente;
use App\Models\Color;
use App\Models\MedioPago;
use App\Models\PlanCuota;
use App\Models\Producto;
use App\Models\PuntoVenta;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Variante;
use App\Models\Vendedor;
use App\Models\Venta;
use App\Services\CuentasCorrientes;
use App\Services\SucursalActiva;
use App\Services\Ventas;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Escenario común de los tests de ventas: una sucursal con su punto de venta 1, usuarios por
 * rol (el Vendedor marcado como vendedor), medios de pago de cada tipo, una tarjeta de crédito
 * con planes de 1 y 3 cuotas (10 %), y productos con alícuotas de 21 % y 10,5 % con stock.
 */
trait EscenarioVentas
{
    protected const DISPOSITIVO = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';

    protected Sucursal $sucursal;

    protected PuntoVenta $puntoVenta;

    protected User $gerente;

    protected User $cajero;

    protected User $vendedorUser;

    protected Vendedor $vendedor;

    protected MedioPago $efectivo;

    protected MedioPago $debito;

    protected MedioPago $credito;

    protected PlanCuota $unaCuota;

    protected PlanCuota $tresCuotas;

    protected MedioPago $cuentaCorriente;

    protected MedioPago $saldoAFavor;

    protected Variante $negro37;

    protected Variante $negro38;

    protected Variante $media;

    protected function prepararEscenarioVentas(): void
    {
        $this->seed(RolesYPermisosSeeder::class);

        $this->sucursal = Sucursal::factory()->create(['nombre' => Sucursal::PRINCIPAL, 'domicilio' => 'Belgrano 123']);
        $this->puntoVenta = PuntoVenta::factory()->create(['sucursal_id' => $this->sucursal->id, 'numero' => 1, 'descripcion' => 'Mostrador']);

        $this->gerente = User::factory()->conSucursal($this->sucursal, Rol::Gerente)->create(['name' => 'Gabriela Gerente', 'email' => 'gerente@example.com']);
        $this->cajero = User::factory()->conSucursal($this->sucursal, Rol::Cajero)->create(['name' => 'Carlos Cajero']);
        $this->vendedorUser = User::factory()->conSucursal($this->sucursal, Rol::Vendedor)->create(['name' => 'Valeria Vendedora']);
        $this->vendedor = Vendedor::factory()->for($this->vendedorUser)->create();

        $this->efectivo = MedioPago::factory()->efectivo()->create(['nombre' => 'Efectivo']);
        $this->debito = MedioPago::factory()->tipo(TipoMedioPago::TarjetaDebito)->create(['nombre' => 'Tarjeta de débito', 'requiere_referencia' => true]);
        $this->credito = MedioPago::factory()->tipo(TipoMedioPago::TarjetaCredito)->create(['nombre' => 'Tarjeta de crédito']);
        $this->unaCuota = PlanCuota::factory()->for($this->credito, 'medioPago')->create(['cuotas' => 1, 'recargo_pct' => '0.00']);
        $this->tresCuotas = PlanCuota::factory()->for($this->credito, 'medioPago')->create(['cuotas' => 3, 'recargo_pct' => '10.00']);
        $this->cuentaCorriente = MedioPago::factory()->tipo(TipoMedioPago::CuentaCorriente)->create(['nombre' => 'Cuenta corriente']);
        $this->saldoAFavor = MedioPago::factory()->tipo(TipoMedioPago::SaldoAFavor)->create(['nombre' => 'Saldo a favor']);

        $general = AlicuotaIva::factory()->create(['nombre' => 'General', 'porcentaje' => '21.00']);
        $reducida = AlicuotaIva::factory()->create(['nombre' => 'Reducida', 'porcentaje' => '10.50']);
        $bota = Producto::factory()->create(['nombre' => 'Bota Ana', 'alicuota_iva_id' => $general->id]);
        $negro = Color::factory()->create(['nombre' => 'Negro']);
        $this->negro37 = Variante::factory()->for($bota)->for($negro)->create(['talle' => '37', 'sku' => 'BOTA-ANA-N37', 'codigo_barras' => '7790000000037', 'precio_final' => '12100.00', 'costo' => '6000.00']);
        $this->negro38 = Variante::factory()->for($bota)->for($negro)->create(['talle' => '38', 'sku' => 'BOTA-ANA-N38', 'codigo_barras' => '7790000000038', 'precio_final' => '12100.00', 'costo' => '6000.00']);
        $medias = Producto::factory()->create(['nombre' => 'Media térmica', 'alicuota_iva_id' => $reducida->id]);
        $this->media = Variante::factory()->for($medias)->for(Color::factory()->create(['nombre' => 'Gris']))->create(['sku' => 'MEDIA-GRIS', 'precio_final' => '11050.00', 'costo' => '4000.00']);

        foreach ([$this->negro37, $this->negro38, $this->media] as $variante) {
            Stock::factory()->create(['variante_id' => $variante->id, 'sucursal_id' => $this->sucursal->id, 'cantidad' => 5]);
        }
    }

    /**
     * Sesión de caja abierta para el usuario en la sucursal del escenario.
     */
    protected function abrirCaja(User $user): CajaSesion
    {
        $caja = Caja::factory()->create(['sucursal_id' => $this->sucursal->id]);

        return CajaSesion::factory()->for($caja)->for($user)->create();
    }

    /**
     * Fija usuario, sucursal activa y dispositivo para llamar a los servicios fuera de HTTP.
     */
    protected function operarComo(User $user): void
    {
        $this->actingAs($user);
        app(SucursalActiva::class)->establecer($this->sucursal, $user);
        request()->attributes->set(IdentificarDispositivo::ATRIBUTO, self::DISPOSITIVO);
    }

    /**
     * Registra una venta con el servicio, como el usuario autenticado.
     *
     * @param  list<array{variante_id: int, cantidad: int, descuento?: string}>  $items
     * @param  list<array<string, mixed>>  $pagos
     */
    protected function vender(array $items, array $pagos, ?Cliente $cliente = null, string $descuentoGeneral = '0', ?User $autorizador = null, ?Vendedor $vendedor = null): Venta
    {
        return app(Ventas::class)->registrar(
            $this->sucursal,
            $this->puntoVenta,
            $vendedor ?? $this->vendedor,
            $cliente,
            array_map(fn (array $item) => $item + ['descuento' => '0'], $items),
            $descuentoGeneral,
            array_map(fn (array $pago) => $pago + ['plan_cuota_id' => null, 'referencia' => null, 'lote' => null, 'cupon' => null], $pagos),
            $autorizador,
        );
    }

    /**
     * @return array{medio_pago_id: int, importe: string}
     */
    protected function pago(MedioPago $medio, string $importe, array $extra = []): array
    {
        return ['medio_pago_id' => $medio->id, 'importe' => $importe] + $extra;
    }

    protected function stockDe(Variante $variante): int
    {
        return (int) Stock::query()->where('variante_id', $variante->id)->where('sucursal_id', $this->sucursal->id)->value('cantidad');
    }

    /**
     * Cliente con saldo a favor acreditado con el servicio de cuentas corrientes.
     */
    protected function clienteConSaldoAFavor(string $saldo): Cliente
    {
        $cliente = Cliente::factory()->create(['nombre' => 'María López']);
        DB::transaction(fn () => app(CuentasCorrientes::class)->acreditar($cliente, $saldo, 'Devolución anterior', sucursal: $this->sucursal));

        return $cliente;
    }
}
