<?php

namespace App\Http\Controllers\Catalogo;

use App\Enums\Parametro;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\StoreProductoRequest;
use App\Http\Requests\Catalogo\UpdateProductoRequest;
use App\Models\AlicuotaIva;
use App\Models\Categoria;
use App\Models\Color;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Temporada;
use App\Models\Variante;
use App\Services\CalculadoraIva;
use App\Services\GeneradorVariantes;
use App\Services\Parametros;
use App\Services\RegistradorAuditoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductoController extends Controller
{
    /**
     * Listado con búsqueda por nombre, SKU o código de barras exacto y filtros.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Producto::class);

        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'categoria_id' => ['nullable', 'integer'],
            'marca_id' => ['nullable', 'integer'],
            'estado' => ['nullable', 'in:activos,inactivos,todos'],
        ]);
        $estado = $filtros['estado'] ?? 'activos';

        $productos = Producto::query()
            ->with(['categoria:id,nombre', 'marca:id,nombre', 'fotoPrincipal'])
            ->withCount('variantes')
            ->when($filtros['q'] ?? null, function (Builder $query, string $texto) {
                $query->where(function (Builder $query) use ($texto) {
                    $query->where('nombre', 'like', '%'.$texto.'%')
                        ->orWhereHas('variantes', fn (Builder $variantes) => $variantes
                            ->where('sku', $texto)
                            ->orWhere('codigo_barras', $texto));
                });
            })
            ->when($filtros['categoria_id'] ?? null, fn (Builder $query, int $id) => $query->where('categoria_id', $id))
            ->when($filtros['marca_id'] ?? null, fn (Builder $query, int $id) => $query->where('marca_id', $id))
            ->when($estado !== 'todos', fn (Builder $query) => $query->where('activo', $estado === 'activos'))
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString();

        return view('catalogo.productos.index', [
            'productos' => $productos,
            'filtros' => [...$filtros, 'estado' => $estado],
            'categorias' => Categoria::query()->orderBy('nombre')->get(['id', 'nombre']),
            'marcas' => Marca::query()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function create(Parametros $parametros): View
    {
        Gate::authorize('create', Producto::class);

        return view('catalogo.productos.create', [
            ...$this->opciones(),
            'colores' => Color::activos()->orderBy('nombre')->get(['id', 'nombre']),
            'usaTalles' => $parametros->habilitado(Parametro::UsarTalles),
        ]);
    }

    public function store(StoreProductoRequest $request, GeneradorVariantes $generador, RegistradorAuditoria $auditoria): RedirectResponse
    {
        $producto = DB::transaction(function () use ($request, $generador, $auditoria) {
            $producto = Producto::create($request->datosProducto());

            $resultado = $generador->crear(
                $producto,
                $request->colores(),
                $request->talles(),
                (string) $request->validated('costo'),
                (string) $request->validated('precio_final'),
            );

            $auditoria->registrar('producto.creado', $producto, null, [
                ...$producto->only('nombre', 'categoria_id', 'marca_id', 'temporada_id', 'alicuota_iva_id', 'activo'),
                'costo' => $request->validated('costo'),
                'precio_final' => $request->validated('precio_final'),
                'variantes' => array_map(fn (Variante $variante) => $variante->descripcion(), $resultado['creadas']),
            ]);

            return $producto;
        });

        return redirect()->route('catalogo.productos.show', $producto)
            ->with('status', 'Producto creado con '.$producto->variantes()->count().' variantes.');
    }

    public function show(Producto $producto, CalculadoraIva $calculadora): View
    {
        Gate::authorize('view', $producto);

        $producto->load(['categoria', 'marca', 'temporada', 'alicuotaIva']);
        $variantes = $producto->variantes()
            ->with('color')
            ->join('colores', 'colores.id', '=', 'variantes.color_id')
            ->orderBy('colores.nombre')
            ->orderByRaw('LENGTH(variantes.talle), variantes.talle')
            ->select('variantes.*')
            ->get();

        return view('catalogo.productos.show', [
            'producto' => $producto,
            'variantes' => $variantes,
            'fotos' => $producto->fotos()->with('color:id,nombre')->get()->each->setRelation('producto', $producto),
            'coloresProducto' => $variantes->pluck('color')->unique('id')->sortBy('nombre')->values(),
            'desgloses' => $variantes->mapWithKeys(fn (Variante $variante) => [
                $variante->id => $calculadora->desglosar($variante->precio_final, $producto->alicuotaIva->porcentaje),
            ]),
        ]);
    }

    public function edit(Producto $producto): View
    {
        Gate::authorize('update', $producto);

        return view('catalogo.productos.edit', [
            ...$this->opciones($producto),
            'producto' => $producto,
        ]);
    }

    public function update(UpdateProductoRequest $request, Producto $producto, RegistradorAuditoria $auditoria): RedirectResponse
    {
        DB::transaction(function () use ($request, $producto, $auditoria) {
            $producto->fill($request->datosProducto());
            $cambios = $producto->getDirty();

            if ($cambios === []) {
                return;
            }

            $antes = array_intersect_key($producto->getOriginal(), $cambios);
            $producto->save();

            $auditoria->registrar('producto.actualizado', $producto, $antes, $cambios);
        });

        return redirect()->route('catalogo.productos.show', $producto)->with('status', 'Cambios guardados.');
    }

    /**
     * Opciones activas de los desplegables, más las que el producto ya tiene aunque estén inactivas.
     *
     * @return array<string, mixed>
     */
    private function opciones(?Producto $producto = null): array
    {
        return [
            'categorias' => Categoria::activosOIncluido($producto?->categoria_id)->orderBy('nombre')->get(['id', 'nombre']),
            'marcas' => Marca::activosOIncluido($producto?->marca_id)->orderBy('nombre')->get(['id', 'nombre']),
            'temporadas' => Temporada::activosOIncluido($producto?->temporada_id)->orderBy('nombre')->get(['id', 'nombre']),
            'alicuotas' => AlicuotaIva::activosOIncluido($producto?->alicuota_iva_id)->orderByDesc('porcentaje')->get(['id', 'nombre', 'porcentaje']),
        ];
    }
}
