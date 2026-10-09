<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarAuditoriaRequest;
use App\Models\Auditoria;
use App\Models\User;
use App\Services\SucursalActiva;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    /**
     * Registros de auditoría de la sucursal activa, del más reciente al más antiguo.
     */
    public function index(ConsultarAuditoriaRequest $request, SucursalActiva $sucursalActiva): View
    {
        $sucursalId = $sucursalActiva->id();
        $filtros = $request->validated();

        $registros = Auditoria::query()
            ->deSucursal($sucursalId)
            ->with('user:id,name')
            ->when($filtros['desde'] ?? null, fn ($query, $desde) => $query->where('created_at', '>=', Carbon::parse($desde)->startOfDay()))
            ->when($filtros['hasta'] ?? null, fn ($query, $hasta) => $query->where('created_at', '<=', Carbon::parse($hasta)->endOfDay()))
            ->when($filtros['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filtros['accion'] ?? null, fn ($query, $accion) => $query->where('accion', $accion))
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('auditoria.index', [
            'registros' => $registros,
            'filtros' => $filtros,
            'usuarios' => User::query()
                ->whereHas('sucursales', fn ($query) => $query->whereKey($sucursalId))
                ->orderBy('name')
                ->get(['id', 'name']),
            'acciones' => Auditoria::query()->deSucursal($sucursalId)->distinct()->orderBy('accion')->pluck('accion'),
        ]);
    }
}
