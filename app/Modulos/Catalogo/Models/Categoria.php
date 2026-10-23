<?php

namespace App\Modulos\Catalogo\Models;

use App\Modulos\Nucleo\Models\Empresa;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use App\Modulos\Nucleo\Models\Concerns\EsDeEmpresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Categoria extends Model
{
    use Auditable, HasFactory, EsDeEmpresa, SoftDeletes;

    protected $fillable = ['empresa_id', 'nombre', 'descripcion'];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }
}