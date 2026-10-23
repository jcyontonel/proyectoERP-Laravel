<?php

namespace App\Modulos\Erp\Models;

use App\Modulos\Nucleo\Models\Empresa;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use App\Modulos\Nucleo\Models\Concerns\EsActivable;
use App\Modulos\Nucleo\Models\Concerns\EsDeEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Caja extends Model
{
    use Auditable, EsActivable, EsDeEmpresa, SoftDeletes;

    protected $fillable = ['empresa_id', 'almacen_id', 'codigo', 'nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class)->withTrashed();
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(CajaSesion::class);
    }

    /** Turno abierto en esta caja (como máximo uno) */
    public function sesionAbierta(): HasOne
    {
        return $this->hasOne(CajaSesion::class)->where('estado', CajaSesion::ABIERTA);
    }
}
