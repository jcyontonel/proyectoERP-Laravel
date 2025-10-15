<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleKardex extends Model
{
    use HasFactory;

    protected $table = 'detalle_kardex';

    protected $fillable = [
        'kardex_id',
        'producto_id',
        'cantidad',
        'costo_unitario',
        'total',
        'lote',
        'fecha_vencimiento',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'costo_unitario' => 'decimal:2',
        'total' => 'decimal:2',
        'fecha_vencimiento' => 'date',
    ];

    // 🔗 Relaciones
    public function kardex()
    {
        return $this->belongsTo(Kardex::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
