<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kardex extends Model
{
    use HasFactory;

    protected $table = 'kardex';

    protected $fillable = [
        'empresa_id',
        'producto_id',
        'tipo_movimiento',
        'motivo',
        'referencia_tipo',
        'referencia_id',
        'referencia_serie',
        'referencia_numero',
        'cantidad',
        'costo_unitario',
        'costo_total',
        'saldo_cantidad',
        'saldo_valorizado',
        'observacion',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'costo_unitario' => 'decimal:2',
        'costo_total' => 'decimal:2',
        'saldo_cantidad' => 'decimal:2',
        'saldo_valorizado' => 'decimal:2',
    ];

    // 🔗 Relaciones
    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function detalles()
    {
        return $this->hasMany(DetalleKardex::class);
    }
}
