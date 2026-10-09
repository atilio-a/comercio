<?php

namespace App\Http\Controllers;

use App\Enums\Parametro;
use App\Http\Requests\SeleccionarSucursalRequest;
use App\Services\Parametros;
use App\Services\SucursalActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SucursalActivaController extends Controller
{
    /**
     * Pantalla para elegir la sucursal activa entre las asignadas al usuario.
     */
    public function edit(Request $request, Parametros $parametros, SucursalActiva $sucursalActiva): View
    {
        abort_unless($parametros->habilitado(Parametro::MultiSucursal), 404);

        return view('sucursal-activa.edit', [
            'sucursales' => $request->user()->sucursales()->activas()->orderBy('nombre')->get(),
            'actual' => $sucursalActiva->id(),
        ]);
    }

    /**
     * Cambia la sucursal activa de la sesión.
     */
    public function update(SeleccionarSucursalRequest $request): RedirectResponse
    {
        $request->session()->put(SucursalActiva::CLAVE_SESION, $request->integer('sucursal_id'));

        return redirect()->route('dashboard');
    }
}
