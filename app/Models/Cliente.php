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

    public function empresas()
    {
        return $this->belongsToMany(Empresa::class, 'cliente_empresa')->withTimestamps();;
    }
     
    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id');
    }
    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }
    
}
