<?php

namespace App\Modulos\Erp\Models;

use App\Modulos\Nucleo\Models\Empresa;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use App\Modulos\Nucleo\Models\Concerns\EsActivable;
use App\Modulos\Nucleo\Models\Concerns\EsDeEmpresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MotivoAjuste extends Model
{
    use Auditable, HasFactory, EsActivable, EsDeEmpresa, SoftDeletes;

    protected $table = 'motivos_ajuste';

    protected $fillable = ['empresa_id', 'codigo', 'nombre', 'tipo_movimiento', 'requiere_aprobacion', 'activo'];

    protected function casts(): array
    {
        return ['requiere_aprobacion' => 'boolean', 'activo' => 'boolean'];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
