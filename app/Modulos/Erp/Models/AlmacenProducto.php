<?php

namespace App\Modulos\Erp\Models;

use App\Modulos\Catalogo\Models\Producto;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Stock de un producto en un almacén */
class AlmacenProducto extends Model
{
    protected $table = 'almacen_producto';

    protected $fillable = ['almacen_id', 'producto_id', 'stock', 'stock_minimo'];

    protected function casts(): array
    {
        return ['stock' => 'float', 'stock_minimo' => 'float'];
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}