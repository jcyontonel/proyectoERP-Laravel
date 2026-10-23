<?php

namespace App\Modulos\Erp\Models;

use App\Modulos\Catalogo\Models\Producto;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleTransferencia extends Model
{
    protected $table = 'detalle_transferencias';

    protected $fillable = [
        'transferencia_id', 'producto_id', 'lote_id', 'cantidad_solicitada', 'cantidad_enviada', 'cantidad_recibida', 'costo_unitario',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_solicitada' => 'float',
            'cantidad_enviada'    => 'float',
            'cantidad_recibida'   => 'float',
            'costo_unitario'      => 'float',
        ];
    }

    public function transferencia(): BelongsTo
    {
        return $this->belongsTo(TransferenciaAlmacen::class, 'transferencia_id');
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
