<?php

namespace App\Modulos\Erp\Models;

use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Catalogo\Models\TipoUnidad;
use App\Modulos\Nucleo\Models\Impuesto;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetalleVenta extends Model
{
    use SoftDeletes;

    protected $table = 'detalle_ventas';

    protected $fillable = [
        'venta_id', 'producto_id', 'lote_id', 'tipo_unidad_id', 'descripcion', 'cantidad',
        'precio_unitario', 'costo_unitario', 'descuento', 'subtotal', 'total_impuestos', 'total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'        => 'float',
            'precio_unitario' => 'float',
            'costo_unitario'  => 'float',
            'descuento'       => 'float',
            'subtotal'        => 'float',
            'total_impuestos' => 'float',
            'total'           => 'float',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /** withTrashed: una venta antigua debe poder leer productos ya eliminados */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class)->withTrashed();
    }

    public function tipoUnidad(): BelongsTo
    {
        return $this->belongsTo(TipoUnidad::class, 'tipo_unidad_id');
    }

    /** Afectación del IGV (catálogo SUNAT 07) para la facturación electrónica */
    public function impuesto(): BelongsTo
    {
        return $this->belongsTo(Impuesto::class);
    }
}
