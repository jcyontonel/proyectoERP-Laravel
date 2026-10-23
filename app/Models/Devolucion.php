<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\EsDeEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Movimiento físico de una devolución (la parte tributaria es la nota de crédito) */
class Devolucion extends Model
{
    use Auditable, EsDeEmpresa, SoftDeletes;

    public const DE_VENTA  = 'devolucion_venta';
    public const DE_COMPRA = 'devolucion_compra';

    protected $table = 'devoluciones';

    protected $fillable = [
        'empresa_id', 'almacen_id', 'venta_id', 'compra_id', 'nota_id', 'user_id', 'serie', 'numero', 'tipo',
        'estado', 'fecha_movimiento', 'motivo',
    ];

    protected function casts(): array
    {
        return ['fecha_movimiento' => 'datetime', 'numero' => 'integer'];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleDevolucion::class);
    }

    public function nota(): BelongsTo
    {
        return $this->belongsTo(Nota::class, 'nota_id');
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }
}
