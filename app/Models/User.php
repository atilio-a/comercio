<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Rol;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'activo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /**
     * Sucursales a las que el usuario está asignado.
     *
     * @return BelongsToMany<Sucursal, $this>
     */
    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class)->withTimestamps();
    }

    /**
     * Registro de vendedor (VND-01), si el usuario fue marcado como vendedor alguna vez.
     *
     * @return HasOne<Vendedor, $this>
     */
    public function vendedor(): HasOne
    {
        return $this->hasOne(Vendedor::class);
    }

    /**
     * Indica si el usuario es un vendedor activo.
     */
    public function esVendedor(): bool
    {
        return (bool) $this->vendedor?->activo;
    }

    /**
     * Usuarios activos con rol Administrador en alguna sucursal activa.
     *
     * @param  Builder<User>  $query
     */
    public function scopeAdministradoresActivos(Builder $query): void
    {
        $tablas = config('permission.table_names');
        $equipo = config('permission.column_names.team_foreign_key');

        $query->where('activo', true)->whereExists(function ($sub) use ($tablas, $equipo) {
            $sub->selectRaw('1')
                ->from($tablas['model_has_roles'].' as mhr')
                ->join($tablas['roles'].' as r', 'r.id', '=', 'mhr.role_id')
                ->join('sucursales as s', 's.id', '=', 'mhr.'.$equipo)
                ->whereColumn('mhr.model_id', 'users.id')
                ->where('mhr.model_type', (new static)->getMorphClass())
                ->where('r.name', Rol::Administrador->value)
                ->where('s.activa', true);
        });
    }

    /**
     * Reemplaza el rol del usuario en una sucursal (un rol por sucursal).
     */
    public function asignarRol(Rol $rol, Sucursal $sucursal): void
    {
        $this->enSucursal($sucursal, fn () => $this->syncRoles([$rol->value]));
    }

    /**
     * Quita el rol del usuario en una sucursal.
     */
    public function quitarRol(Sucursal $sucursal): void
    {
        $this->enSucursal($sucursal, fn () => $this->syncRoles([]));
    }

    /**
     * Rol del usuario en una sucursal, si tiene.
     */
    public function rolEn(Sucursal $sucursal): ?Rol
    {
        return $this->enSucursal(
            $sucursal,
            fn () => Rol::tryFrom((string) $this->roles()->value('name')),
        );
    }

    /**
     * Ejecuta una operación de spatie en otra sucursal y restaura la sucursal anterior.
     *
     * @template T
     *
     * @param  callable(): T  $operacion
     * @return T
     */
    private function enSucursal(Sucursal $sucursal, callable $operacion): mixed
    {
        $anterior = getPermissionsTeamId();

        setPermissionsTeamId($sucursal->id);
        $this->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $operacion();
        } finally {
            setPermissionsTeamId($anterior);
            $this->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
