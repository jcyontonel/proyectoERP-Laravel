<?php

namespace App\Modulos\Nucleo\Models;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use App\Modulos\Nucleo\Models\Concerns\EsActivable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Auditable;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles, EsActivable, SoftDeletes;

    /**
     * El rol NO va aquí: se asigna por empresa con Spatie (model_has_roles.empresa_id).
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'apellido', 'telefono', 'email', 'password', 'activo'];

    /**
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'activo'            => 'boolean',
        ];
    }

    public function preferenciasRel(): HasOne
    {
        return $this->hasOne(PreferenciaUsuario::class);
    }

    /** Preferencias del usuario (los valores por defecto si aún no guardó ninguna) */
    public function preferencias(): PreferenciaUsuario
    {
        return $this->preferenciasRel ?? new PreferenciaUsuario(['user_id' => $this->id]);
    }

    /** Empresas a las que tiene acceso (tabla empresa_user) */
    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_user')->withTimestamps();
    }

    /** "Jhonnatan Yontonel" */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => trim($this->name . ' ' . ($this->apellido ?? '')));
    }

    /** "JY" para el avatar */
    protected function iniciales(): Attribute
    {
        return Attribute::get(fn () => str($this->nombre_completo)->explode(' ')->filter()
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->join(''));
    }

    /** Rol en la empresa actual (el team de Spatie lo fija el middleware EstablecerEmpresaActual) */
    public function rolActual(): ?string
    {
        return $this->getRoleNames()->first();
    }
}
