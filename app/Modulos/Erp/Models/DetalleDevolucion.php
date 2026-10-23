<?php

namespace App\Modulos\Erp\Models;

use App\Modulos\Catalogo\Models\Producto;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleDevolucion extends Model
{
    protected $table = 'detalle_devoluciones';

    protected $fillable = ['devolucion_id', 'producto_id', 'lote_id', 'cantidad', 'precio_unitario', 'costo_unitario', 'total', 'disposicion'];

    protected function casts(): array
    {
        return ['cantidad' => 'float', 'precio_unitario' => 'float', 'costo_unitario' => 'float', 'total' => 'float'];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class)->withTrashed();
    }
}
