<?php

namespace App\Http\Controllers\Catalogo;

use App\Enums\Parametro;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\AgregarVariantesRequest;
use App\Http\Requests\Catalogo\UpdateVarianteRequest;
use App\Models\Color;
use App\Models\Producto;
use App\Models\Variante;
use App\Services\CalculadoraIva;
use App\Services\GeneradorVariantes;
use App\Services\Parametros;
use App\Services\RegistradorAuditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VarianteController extends Controller
{
    /**
     * Agregar variantes a un producto existente con la grilla color × talle.
     */
    public function create(Producto $producto, Parametros $parametros): View
    {
        Gate::authorize('update', $producto);

        return view('catalogo.variantes.create', [
            'producto' => $producto,
            'colores' => Color::activos()->orderBy('nombre')->get(['id', 'nombre']),
            'usaTalles' => $parametros->habilitado(Parametro::UsarTalles),
        ]);
    }

    public function store(AgregarVariantesRequest $request, Producto $producto, GeneradorVariantes $generador, RegistradorAuditoria $auditoria): RedirectResponse
    {
        $resultado = DB::transaction(function () use ($request, $producto, $generador, $auditoria) {
            $resultado = $generador->crear(
                $producto,
                $request->colores(),
                $request->talles(),
                (string) $request->validated('costo'),
                (string) $request->validated('precio_final'),
            );

            foreach ($resultado['creadas'] as $variante) {
                $auditoria->registrar('variante.creada', $variante, null, [
                    'producto_id' => $producto->id,
                    'variante' => $variante->descripcion(),
                    ...$variante->only('sku', 'codigo_barras', 'costo', 'precio_final'),
                ]);
            }

            return $resultado;
        });

        $mensaje = 'Se crearon '.count($resultado['creadas']).' variantes.';

        if ($resultado['omitidas'] !== []) {
            $mensaje .= ' Ya existían: '.implode(', ', $resultado['omitidas']).'.';
        }

        return redirect()->route('catalogo.productos.show', $producto)->with('status', $mensaje);
    }

    public function edit(Producto $producto, Variante $variante, CalculadoraIva $calculadora): View
    {
        Gate::authorize('update', $producto);

        return view('catalogo.variantes.edit', [
            'producto' => $producto->load('alicuotaIva'),
            'variante' => $variante->load('color'),
            'desglose' => $calculadora->desglosar($variante->precio_final, $producto->alicuotaIva->porcentaje),
        ]);
    }

    public function update(UpdateVarianteRequest $request, Producto $producto, Variante $variante, RegistradorAuditoria $auditoria): RedirectResponse
    {
        DB::transaction(function () use ($request, $variante, $auditoria) {
            $variante->fill($request->datosVariante());
            $cambios = $variante->getDirty();

            if ($cambios === []) {
                return;
            }

            $antes = array_intersect_key($variante->getOriginal(), $cambios);
            $variante->save();

            $auditoria->registrar('variante.actualizada', $variante, $antes, $variante->only(array_keys($cambios)));
        });

        return redirect()->route('catalogo.productos.show', $producto)->with('status', 'Variante actualizada.');
    }
}
