<?php

namespace App\Modulos\Catalogo\Models;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoUnidad extends Model
{
    use Auditable, HasFactory;

    protected $table = 'tipo_unidades';

    protected $fillable = ['codigo', 'codigo_sunat', 'descripcion'];
}
