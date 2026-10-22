<?php

namespace App\Models;

use App\Models\Concerns\EsActivable;
use App\Models\Concerns\EsDeEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lote extends Model
{
    use EsActivable, EsDeEmpresa, SoftDeletes;

    protected $fillable = ['empresa_id', 'producto_id', 'codigo_lote', 'fecha_fabricacion', 'fecha_vencimiento', 'activo'];

    protected function casts(): array
    {
        return [
            'fecha_fabricacion' => 'date',
            'fecha_vencimiento' => 'date',
            'activo'            => 'boolean',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class)->withTrashed();
    }

    public function existencias(): HasMany
    {
        return $this->hasMany(ExistenciaLote::class);
    }
}
