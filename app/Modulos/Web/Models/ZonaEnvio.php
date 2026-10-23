<?php

namespace App\Modulos\Web\Models;

use App\Modulos\Nucleo\Models\Concerns\EsActivable;
use App\Modulos\Nucleo\Models\Concerns\EsDeEmpresa;
use Illuminate\Database\Eloquent\Model;

/** Zona de reparto de la tienda con su costo y tiempo de entrega */
class ZonaEnvio extends Model
{
    use EsActivable, EsDeEmpresa;

    protected $table = 'zonas_envio';

    protected $fillable = ['empresa_id', 'nombre', 'costo', 'tiempo_entrega', 'activo', 'orden'];

    protected function casts(): array
    {
        return ['costo' => 'float', 'activo' => 'boolean', 'orden' => 'integer'];
    }
}
