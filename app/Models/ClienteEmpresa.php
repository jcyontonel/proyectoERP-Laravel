<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClienteEmpresa extends Pivot
{
    protected $table = 'cliente_empresa';
    protected $fillable = ['cliente_id', 'empresa_id', 'fecha_asociacion'];
}
