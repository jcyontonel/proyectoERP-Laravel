<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Compra extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'empresa_id',
        'proveedor_id',
        'user_id',
        'serie',
        'numero',
        'fecha_emision',
        'tipo_comprobante',
        'moneda',
        'subtotal',
        'total_impuestos',
        'total',
        'estado',
        'observacion',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'subtotal' => 'decimal:2',
        'total_impuestos' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // 🔗 Relaciones
    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleCompra::class);
    }
}
