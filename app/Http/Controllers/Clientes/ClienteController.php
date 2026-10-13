<?php

namespace App\Http\Controllers\Clientes;

use App\Enums\CondicionIva;
use App\Enums\Parametro;
use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clientes\GuardarClienteRequest;
use App\Models\Cliente;
use App\Services\Parametros;
use App\Services\RegistradorAuditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Clientes (CLI-01): los ve quien tiene `clientes.ver` y los crea o modifica quien tiene
 * `clientes.gestionar`. La ficha muestra la cuenta corriente solo con `cuentas_corrientes.ver`.
 */
class ClienteController extends Controller
{
    private const CAMPOS_AUDITADOS = [
        'nombre', 'dni_cuit', 'telefono', 'email', 'talle_habitual', 'condicion_iva', 'observaciones', 'activo',
    ];

    public function __construct(private Parametros $parametros) {}

    public function index(Request $request): View
    {
        Gate::authorize(Permiso::ClientesVer->value);

        $filtros = $request->validate(['q' => ['nullable', 'string', 'max:150']]);

        $clientes = Cliente::query()
            ->when($filtros['q'] ?? null, fn ($query, string $texto) => $query->buscar($texto))
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->paginate(50)
            ->withQueryString();

        return view('clientes.index', [
            'clientes' => $clientes,
            'filtros' => $filtros,
            'usarTalles' => $this->usarTalles(),
            'puedeGestionar' => $request->user()->can(Permiso::ClientesGestionar->value),
        ]);
    }

    public function create(): View
    {
        Gate::authorize(Permiso::ClientesGestionar->value);

        return view('clientes.form', $this->datosFormulario(null));
    }

    public function store(GuardarClienteRequest $request, RegistradorAuditoria $auditoria): RedirectResponse
    {
        $cliente = $this->crear($request, $auditoria);

        return redirect()->route('clientes.show', $cliente)->with('status', 'Cliente creado.');
    }

    /**
     * Alta rápida desde la venta (JSON): nombre más teléfono o documento.
     */
    public function storeRapido(GuardarClienteRequest $request, RegistradorAuditoria $auditoria): JsonResponse
    {
        $cliente = $this->crear($request, $auditoria);

        return response()->json(['cliente' => $this->datosJson($cliente)], 201);
    }

    /**
     * Búsqueda de clientes activos para la venta (JSON, máximo 20).
     */
    public function buscar(Request $request): JsonResponse
    {
        Gate::authorize(Permiso::ClientesVer->value);

        $filtros = $request->validate(['q' => ['required', 'string', 'max:150']]);

        $clientes = Cliente::query()
            ->activos()
            ->buscar($filtros['q'])
            ->orderBy('nombre')
            ->limit(20)
            ->get();

        return response()->json([
            'clientes' => $clientes->map(fn (Cliente $cliente) => $this->datosJson($cliente))->values(),
        ]);
    }

    public function show(Request $request, Cliente $cliente): View
    {
        Gate::authorize(Permiso::ClientesVer->value);

        $puedeVerCuenta = $request->user()->can(Permiso::CuentasCorrientesVer->value);
        $cuenta = $puedeVerCuenta ? $cliente->cuentaCorriente : null;

        return view('clientes.show', [
            'cliente' => $cliente,
            'usarTalles' => $this->usarTalles(),
            'puedeGestionar' => $request->user()->can(Permiso::ClientesGestionar->value),
            'puedeVerCuenta' => $puedeVerCuenta,
            'cuenta' => $cuenta,
            'movimientos' => $cuenta?->movimientos()
                ->with(['user:id,name', 'sucursal:id,nombre'])
                ->orderByDesc('id')
                ->paginate(25),
        ]);
    }

    public function edit(Cliente $cliente): View
    {
        Gate::authorize(Permiso::ClientesGestionar->value);

        return view('clientes.form', $this->datosFormulario($cliente));
    }

    public function update(GuardarClienteRequest $request, Cliente $cliente, RegistradorAuditoria $auditoria): RedirectResponse
    {
        DB::transaction(function () use ($request, $cliente, $auditoria) {
            $cliente->fill($request->datos(activoPorDefecto: false));
            $cambios = $cliente->getDirty();

            if ($cambios === []) {
                return;
            }

            $antes = array_intersect_key($cliente->getRawOriginal(), $cambios);
            $cliente->save();

            $auditoria->registrar('clientes.actualizado', $cliente, $antes, $cambios);
        });

        return redirect()->route('clientes.show', $cliente)->with('status', 'Cambios guardados.');
    }

    private function crear(GuardarClienteRequest $request, RegistradorAuditoria $auditoria): Cliente
    {
        return DB::transaction(function () use ($request, $auditoria) {
            $cliente = Cliente::create($request->datos(activoPorDefecto: true));

            $auditoria->registrar('clientes.creado', $cliente, null, array_intersect_key(
                $cliente->getAttributes(),
                array_flip(self::CAMPOS_AUDITADOS),
            ));

            return $cliente;
        });
    }

    /**
     * @return array{cliente: ?Cliente, usarTalles: bool, condiciones: list<CondicionIva>}
     */
    private function datosFormulario(?Cliente $cliente): array
    {
        return [
            'cliente' => $cliente,
            'usarTalles' => $this->usarTalles(),
            'condiciones' => CondicionIva::cases(),
        ];
    }

    /**
     * @return array{id: int, nombre: string, documento: string, telefono: ?string, condicion_iva: string, talle_habitual?: ?string}
     */
    private function datosJson(Cliente $cliente): array
    {
        $datos = [
            'id' => $cliente->id,
            'nombre' => $cliente->nombre,
            'documento' => $cliente->documentoFormateado(),
            'telefono' => $cliente->telefono,
            'condicion_iva' => $cliente->condicion_iva->value,
        ];

        if ($this->usarTalles()) {
            $datos['talle_habitual'] = $cliente->talle_habitual;
        }

        return $datos;
    }

    private function usarTalles(): bool
    {
        return $this->parametros->habilitado(Parametro::UsarTalles);
    }
}
