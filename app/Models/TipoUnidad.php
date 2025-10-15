<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoUnidad extends Model
{
    /** @use HasFactory<\Database\Factories\TipoUnidadFactory> */
    use HasFactory;
    protected $table = 'tipo_unidades';
}
