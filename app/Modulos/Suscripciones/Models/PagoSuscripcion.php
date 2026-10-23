<?php

namespace App\Modulos\Suscripciones\Models;

use App\Modulos\Erp\Models\Nota;
use App\Modulos\Facturacion\Models\Documento;
use App\Modulos\Nucleo\Models\Empresa;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

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

    /** Factura o boleta electrónica del pago (la emite la empresa dueña de la plataforma) */
    public function comprobante(): MorphOne
    {
        return $this->morphOne(Documento::class, 'comprobante')->whereIn('tipo', [Documento::FACTURA, Documento::BOLETA]);
    }

    /** Nota de crédito, si el pago se anuló fuera del plazo de baja */
    public function notaCredito(): MorphOne
    {
        return $this->morphOne(Documento::class, 'comprobante')->where('tipo', Documento::NOTA_CREDITO);
    }

    public function esPagado(): bool
    {
        return $this->estado === self::PAGADO;
    }

    /** El facturador pide la baja cuando un comprobante se acepta y su origen ya está anulado */
    public function esAnulada(): bool
    {
        return $this->estado === self::ANULADO;
    }
}
