<?php

namespace App\Http\Controllers\Ventas;

use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\GuardarPuntoVentaRequest;
use App\Models\PuntoVenta;
use App\Services\RegistradorAuditoria;
use App\Services\SucursalActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Puntos de venta de la sucursal activa (VEN-07). Solo con `puntos_venta.configurar`
 * (Administrador). Los de otra sucursal responden 404.
 */
class PuntoVentaController extends Controller
{
    public function __construct(private SucursalActiva $sucursalActiva) {}

    public function index(): View
    {
        Gate::authorize(Permiso::PuntosVentaConfigurar->value);

        return view('ventas.puntos-venta.index', [
            'puntosVenta' => PuntoVenta::query()
                ->where('sucursal_id', $this->sucursalActiva->id())
                ->orderByDesc('activo')
                ->orderBy('numero')
                ->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize(Permiso::PuntosVentaConfigurar->value);

        return view('ventas.puntos-venta.form', ['puntoVenta' => null]);
    }

    public function store(GuardarPuntoVentaRequest $request, RegistradorAuditoria $auditoria): RedirectResponse
    {
        DB::transaction(function () use ($request, $auditoria) {
            $puntoVenta = PuntoVenta::create([
                'sucursal_id' => $this->sucursalActiva->id(),
                'numero' => (int) $request->validated('numero'),
                'descripcion' => $request->descripcion(),
                'activo' => $request->boolean('activo', true),
            ]);

            $auditoria->registrar('puntos_venta.creado', $puntoVenta, null, $puntoVenta->only('sucursal_id', 'numero', 'descripcion', 'activo'));
        });

        return redirect()->route('ventas.puntos-venta.index')->with('status', 'Punto de venta creado.');
    }

    public function edit(PuntoVenta $puntoVenta): View
    {
        Gate::authorize(Permiso::PuntosVentaConfigurar->value);
        $this->exigirSucursalActiva($puntoVenta);

        return view('ventas.puntos-venta.form', ['puntoVenta' => $puntoVenta]);
    }

    public function update(GuardarPuntoVentaRequest $request, PuntoVenta $puntoVenta, RegistradorAuditoria $auditoria): RedirectResponse
    {
        $this->exigirSucursalActiva($puntoVenta);

        DB::transaction(function () use ($request, $puntoVenta, $auditoria) {
            $puntoVenta->fill([
                'descripcion' => $request->descripcion(),
                'activo' => $request->boolean('activo'),
            ]);
            $cambios = $puntoVenta->getDirty();

            if ($cambios === []) {
                return;
            }

            $antes = array_intersect_key($puntoVenta->getOriginal(), $cambios);
            $puntoVenta->save();

            $auditoria->registrar('puntos_venta.actualizado', $puntoVenta, $antes, $cambios);
        });

        return redirect()->route('ventas.puntos-venta.index')->with('status', 'Cambios guardados.');
    }

    private function exigirSucursalActiva(PuntoVenta $puntoVenta): void
    {
        abort_unless((int) $puntoVenta->sucursal_id === (int) $this->sucursalActiva->id(), 404);
    }
}
