<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    /** @use HasFactory<\Database\Factories\ClienteFactory> */
    use HasFactory;

    protected $fillable = [
        'id',
        'tipo_documento_id',
        'numero_documento',
        'nombre',
        'telefono',
        'correo',
        'direccion',
        'empresa_id', // <- agrega esta línea
    ];
}
