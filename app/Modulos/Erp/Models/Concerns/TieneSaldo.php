<?php

namespace App\Modulos\Erp\Models\Concerns;

use App\Modulos\Erp\Models\Pago;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Saldo de un documento con pagos (Venta: cobros · Compra: pagos a proveedor).
 * Si la consulta trae withSum(['pagosRegistrados as pagado'], 'monto') usa ese valor sin ir a la BD.
 */
trait TieneSaldo
{
    /** Pagos vigentes (no anulados) */
    public function pagosRegistrados(): HasMany
    {
        return $this->hasMany(Pago::class)->where('estado', Pago::REGISTRADO);
    }

    public function montoPagado(): float
    {
        if (array_key_exists('pagado', $this->attributes)) {
            return (float) $this->attributes['pagado'];
        }

        return (float) $this->pagosRegistrados()->sum('monto');
    }

    public function saldo(): float
    {
        return max(round((float) $this->total - $this->montoPagado(), 2), 0);
    }

    /** pagada | parcial | pendiente (null si está anulada) */
    public function estadoPago(): ?string
    {
        if ($this->esAnulada()) {
            return null;
        }

        return match (true) {
            $this->saldo() <= 0      => 'pagada',
            $this->montoPagado() > 0 => 'parcial',
            default                  => 'pendiente',
        };
    }

    /** Días de atraso (0 si no vence aún o no tiene saldo) */
    public function diasVencido(): int
    {
        if (! $this->fecha_vencimiento || $this->saldo() <= 0) {
            return 0;
        }

        return max((int) $this->fecha_vencimiento->startOfDay()->diffInDays(today(), false), 0);
    }

    public function estaVencida(): bool
    {
        return $this->diasVencido() > 0;
    }
}
