<?php

namespace App\Modulos\Web\Models;

use App\Modulos\Catalogo\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetallePedidoWeb extends Model
{
    protected $table = 'detalle_pedidos_web';

    protected $fillable = ['pedido_id', 'producto_id', 'descripcion', 'cantidad', 'precio_unitario', 'total'];

    protected function casts(): array
    {
        return ['cantidad' => 'float', 'precio_unitario' => 'float', 'total' => 'float'];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class)->withTrashed();
    }
}
