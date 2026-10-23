<?php

namespace App\Modulos\Erp\Models;

use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Catalogo\Models\TipoUnidad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Línea de una nota: producto devuelto (con cantidad) o un concepto por monto (sin producto) */
class DetalleNota extends Model
{
    protected $table = 'detalle_notas_credito_debito';

    protected $fillable = [
        'nota_id', 'producto_id', 'lote_id', 'tipo_unidad_id', 'impuesto_id', 'descripcion', 'cantidad',
        'precio_unitario', 'subtotal', 'total_impuestos', 'total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'float', 'precio_unitario' => 'float', 'subtotal' => 'float',
            'total_impuestos' => 'float', 'total' => 'float',
        ];
    }

    public function nota(): BelongsTo
    {
        return $this->belongsTo(Nota::class, 'nota_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class)->withTrashed();
    }

    public function tipoUnidad(): BelongsTo
    {
        return $this->belongsTo(TipoUnidad::class);
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }
}
