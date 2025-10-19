<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetalleVenta extends Model
{
    /** @use HasFactory<\Database\Factories\DetalleFacturaFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'venta_id',
        'producto_id',
        'tipo_unidad_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'total_impuestos',
        'total',
    ];
    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function tipoUnidad()
    {
        return $this->belongsTo(TipoUnidad::class, 'tipo_unidad_id');
    }
}
