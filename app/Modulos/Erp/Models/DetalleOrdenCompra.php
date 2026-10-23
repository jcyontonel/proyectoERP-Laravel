<?php

namespace App\Modulos\Erp\Models;

use App\Modulos\Catalogo\Models\Producto;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleOrdenCompra extends Model
{
    protected $table = 'detalle_ordenes_compra';

    protected $fillable = [
        'orden_compra_id', 'producto_id', 'descripcion', 'cantidad', 'cantidad_recibida', 'costo_unitario',
        'subtotal', 'total_impuestos', 'total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'float', 'cantidad_recibida' => 'float', 'costo_unitario' => 'float',
            'subtotal' => 'float', 'total_impuestos' => 'float', 'total' => 'float',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class)->withTrashed();
    }

    public function pendiente(): float
    {
        return max(round($this->cantidad - $this->cantidad_recibida, 4), 0);
    }
}
