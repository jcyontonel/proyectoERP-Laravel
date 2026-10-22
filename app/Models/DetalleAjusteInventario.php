<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Línea de ajuste: cantidad (+/−) o, en un conteo, stock del sistema vs. stock contado */
class DetalleAjusteInventario extends Model
{
    protected $table = 'detalle_ajustes_inventario';

    protected $fillable = [
        'ajuste_id', 'producto_id', 'lote_id', 'stock_sistema', 'stock_contado', 'cantidad', 'costo_unitario', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'stock_sistema'  => 'float',
            'stock_contado'  => 'float',
            'cantidad'       => 'float',
            'costo_unitario' => 'float',
        ];
    }

    public function ajuste(): BelongsTo
    {
        return $this->belongsTo(AjusteInventario::class, 'ajuste_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class)->withTrashed();
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class)->withTrashed();
    }
}
