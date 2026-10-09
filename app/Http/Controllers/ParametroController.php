<?php

namespace App\Http\Controllers;

use App\Enums\Parametro;
use App\Http\Requests\UpdateParametrosRequest;
use App\Models\Parametro as ParametroGuardado;
use App\Services\Parametros;
use App\Services\RegistradorAuditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ParametroController extends Controller
{
    /**
     * Pantalla de parametrización.
     */
    public function edit(Parametros $parametros): View
    {
        return view('parametros.edit', [
            'definiciones' => Parametro::cases(),
            'valores' => $parametros->todos(),
        ]);
    }

    /**
     * Guarda los parámetros que cambiaron y audita cada cambio en la misma transacción.
     */
    public function update(UpdateParametrosRequest $request, Parametros $parametros, RegistradorAuditoria $auditoria): RedirectResponse
    {
        DB::transaction(function () use ($request, $parametros, $auditoria) {
            foreach ($request->valores() as $clave => $nuevo) {
                $parametro = Parametro::from($clave);

                if ($parametros->habilitado($parametro) === $nuevo) {
                    continue;
                }

                $anterior = $parametros->guardar($parametro, $nuevo);

                $auditoria->registrar(
                    'parametro.modificado',
                    ParametroGuardado::where('clave', $clave)->first(),
                    [$clave => $this->describir($anterior)],
                    [$clave => $this->describir($nuevo)],
                );
            }
        });

        return redirect()->route('parametros.edit')->with('status', 'parametros-guardados');
    }

    private function describir(bool $valor): string
    {
        return $valor ? 'habilitado' : 'deshabilitado';
    }
}
