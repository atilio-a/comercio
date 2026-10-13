<?php

namespace App\Services;

use App\Models\Sucursal;
use App\Models\User;
use App\Models\Vendedor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Vendedores (VND-01): marca de vendedor sobre los usuarios y vendedores disponibles por sucursal.
 */
class Vendedores
{
    public function __construct(
        private SucursalActiva $sucursalActiva,
    ) {}

    /**
     * Vendedores activos cuyo usuario está activo y asignado a la sucursal (la activa si es
     * null), con el usuario cargado y ordenados por nombre. Sin sucursal, ninguno.
     *
     * @return Collection<int, Vendedor>
     */
    public function disponibles(?Sucursal $sucursal = null): Collection
    {
        $sucursalId = $sucursal?->id ?? $this->sucursalActiva->id();

        if ($sucursalId === null) {
            return new Collection;
        }

        return Vendedor::query()
            ->activos()
            ->whereHas('user', function (Builder $query) use ($sucursalId) {
                $query->where('activo', true)
                    ->whereHas('sucursales', fn (Builder $query) => $query->whereKey($sucursalId));
            })
            ->with('user')
            ->get()
            ->sortBy(fn (Vendedor $vendedor) => mb_strtolower($vendedor->user->name))
            ->values();
    }

    /**
     * Marca o desmarca al usuario como vendedor. Desmarcar desactiva el registro sin borrarlo.
     * Devuelve true si cambió algo. No abre transacción ni audita: lo hace quien llama.
     */
    public function marcar(User $user, bool $esVendedor): bool
    {
        $vendedor = Vendedor::query()->where('user_id', $user->id)->first();

        if (! $vendedor) {
            if (! $esVendedor) {
                return false;
            }

            Vendedor::create(['user_id' => $user->id, 'activo' => true]);
            $user->unsetRelation('vendedor');

            return true;
        }

        if ($vendedor->activo === $esVendedor) {
            return false;
        }

        $vendedor->update(['activo' => $esVendedor]);
        $user->unsetRelation('vendedor');

        return true;
    }
}
