<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleCotizacion extends Model
{
    protected $table = 'detalle_cotizaciones';

    protected $fillable = [
        'cotizacion_id', 'producto_id', 'tipo_unidad_id', 'descripcion', 'cantidad', 'precio_unitario',
        'descuento', 'subtotal', 'total_impuestos', 'total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'float', 'precio_unitario' => 'float', 'descuento' => 'float',
            'subtotal' => 'float', 'total_impuestos' => 'float', 'total' => 'float',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class)->withTrashed();
    }

    public function tipoUnidad(): BelongsTo
    {
        return $this->belongsTo(TipoUnidad::class);
    }
}
