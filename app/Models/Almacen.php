<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\EsActivable;
use App\Models\Concerns\EsDeEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Almacen extends Model
{
    use Auditable, EsActivable, EsDeEmpresa, SoftDeletes;

    protected $table = 'almacenes';

    protected $fillable = ['empresa_id', 'codigo', 'nombre', 'direccion', 'es_principal', 'activo'];

    protected function casts(): array
    {
        return ['es_principal' => 'boolean', 'activo' => 'boolean'];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(AlmacenProducto::class);
    }
}
