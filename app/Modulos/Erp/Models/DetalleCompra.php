<?php

namespace App\Modulos\Erp\Models;

use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Catalogo\Models\TipoUnidad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetalleCompra extends Model
{
    use SoftDeletes;

    protected $table = 'detalle_compras';

    protected $fillable = [
        'compra_id', 'producto_id', 'lote_id', 'tipo_unidad_id', 'descripcion',
        'cantidad', 'precio_unitario', 'subtotal', 'impuesto', 'total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'        => 'float',
            'precio_unitario' => 'float',
            'subtotal'        => 'float',
            'impuesto'        => 'float',
            'total'           => 'float',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class)->withTrashed();
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class)->withTrashed();
    }

    public function tipoUnidad(): BelongsTo
    {
        return $this->belongsTo(TipoUnidad::class, 'tipo_unidad_id');
    }
}
