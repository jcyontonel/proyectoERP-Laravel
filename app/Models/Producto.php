<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    /** @use HasFactory<\Database\Factories\ProductoFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'empresa_id',
        'categoria_id',
        'tipo_unidad_id',
        'impuesto_id',
        'codigo',
        'nombre',
        'descripcion',
        'precio_unitario',
        'stock',
        'es_servicio',
        'activo',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function unidad()
    {
        return $this->belongsTo(TipoUnidad::class, 'tipo_unidad_id');
    }

    public function impuesto()
    {
        return $this->belongsTo(Impuesto::class);
    }

    public function tipoUnidad()
    {
        return $this->belongsTo(TipoUnidad::class);
    }

}
