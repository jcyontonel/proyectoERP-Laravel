<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cobro de un periodo de suscripción: pendiente → pagado (extiende la suscripción) | anulado */
class PagoSuscripcion extends Model
{
    use Auditable;

    public const PENDIENTE = 'pendiente';
    public const PAGADO    = 'pagado';
    public const ANULADO   = 'anulado';

    protected $table = 'pagos_suscripcion';

    protected $fillable = [
        'empresa_id', 'suscripcion_id', 'oferta_id', 'periodo_desde', 'periodo_hasta', 'meses', 'monto_bruto',
        'descuento', 'monto', 'estado', 'metodo', 'referencia', 'fecha_pago', 'registrado_por', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'periodo_desde' => 'date', 'periodo_hasta' => 'date', 'meses' => 'integer', 'monto_bruto' => 'float',
            'descuento' => 'float', 'monto' => 'float', 'fecha_pago' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function suscripcion(): BelongsTo
    {
        return $this->belongsTo(Suscripcion::class);
    }

    public function oferta(): BelongsTo
    {
        return $this->belongsTo(Oferta::class);
    }
}
