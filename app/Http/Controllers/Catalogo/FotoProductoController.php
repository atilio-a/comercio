<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\ActualizarFotoRequest;
use App\Http\Requests\Catalogo\SubirFotosRequest;
use App\Models\Color;
use App\Models\Producto;
use App\Models\ProductoFoto;
use App\Services\GaleriaProducto;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class FotoProductoController extends Controller
{
    public function store(SubirFotosRequest $request, Producto $producto, GaleriaProducto $galeria): RedirectResponse
    {
        try {
            $fotos = $galeria->agregar(
                $producto,
                $request->file('fotos'),
                (array) $request->file('miniaturas', []),
                $request->filled('color_id') ? Color::find($request->integer('color_id')) : null,
            );
        } catch (DomainException $error) {
            return back()->withErrors(['fotos' => $error->getMessage()]);
        }

        return redirect()->to(route('catalogo.productos.show', $producto).'#imagenes')
            ->with('status', $fotos->count() === 1 ? 'Se agregó 1 imagen.' : "Se agregaron {$fotos->count()} imágenes.");
    }

    public function principal(Producto $producto, ProductoFoto $foto, GaleriaProducto $galeria): RedirectResponse
    {
        Gate::authorize('update', $producto);

        $galeria->marcarPrincipal($foto);

        return redirect()->to(route('catalogo.productos.show', $producto).'#imagenes')->with('status', 'Imagen principal actualizada.');
    }

    public function color(ActualizarFotoRequest $request, Producto $producto, ProductoFoto $foto, GaleriaProducto $galeria): RedirectResponse
    {
        $galeria->cambiarColor($foto, $request->filled('color_id') ? Color::find($request->integer('color_id')) : null);

        return redirect()->to(route('catalogo.productos.show', $producto).'#imagenes')->with('status', 'Color de la imagen actualizado.');
    }

    public function destroy(Producto $producto, ProductoFoto $foto, GaleriaProducto $galeria): RedirectResponse
    {
        Gate::authorize('update', $producto);

        $galeria->eliminar($foto);

        return redirect()->to(route('catalogo.productos.show', $producto).'#imagenes')->with('status', 'Imagen eliminada.');
    }

    /**
     * Entrega el archivo desde el disco privado, solo a quien puede ver el producto.
     */
    public function mostrar(Request $request, ProductoFoto $foto, string $tamano): Response
    {
        Gate::authorize('view', $foto->producto);

        $ruta = $tamano === 'miniatura' ? $foto->ruta_miniatura : $foto->ruta;
        $etag = '"'.sha1($ruta.'|'.$foto->updated_at?->timestamp).'"';
        $cabeceras = ['Cache-Control' => 'private, max-age=86400', 'ETag' => $etag];

        if ($request->headers->get('If-None-Match') === $etag) {
            return response()->noContent(304)->withHeaders($cabeceras);
        }

        abort_unless(Storage::disk(GaleriaProducto::DISCO)->exists($ruta), 404);

        return Storage::disk(GaleriaProducto::DISCO)->response($ruta, null, $cabeceras);
    }
}
