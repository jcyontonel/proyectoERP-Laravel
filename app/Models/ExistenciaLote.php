<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cantidad de un lote en un almacén */
class ExistenciaLote extends Model
{
    protected $table = 'existencias_lote';

    protected $fillable = ['lote_id', 'almacen_id', 'cantidad', 'costo_unitario'];

    protected function casts(): array
    {
        return ['cantidad' => 'float', 'costo_unitario' => 'float'];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }
}
