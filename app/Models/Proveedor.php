<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proveedor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'proveedores';

    protected $fillable = [
        'empresa_id',
        'tipo_documento_id',
        'numero_documento',
        'razon_social',
        'nombre_comercial',
        'contacto_nombre',
        'contacto_cargo',
        'telefono',
        'celular',
        'email',
        'pais',
        'departamento',
        'provincia',
        'distrito',
        'direccion',
        'observacion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // 🔗 Relaciones
    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    public function compras()
    {
        return $this->hasMany(Compra::class);
    }
}
