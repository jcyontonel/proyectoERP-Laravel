<?php

namespace App\Modulos\Erp\Models;

use App\Modulos\Nucleo\Models\User;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use App\Modulos\Nucleo\Models\Concerns\EsDeEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Cobros (ventas) y pagos (compras). Con caja_sesion_id cuentan en el arqueo del turno. */
class Pago extends Model
{
    use Auditable, EsDeEmpresa, SoftDeletes;

    public const TIPO_COBRO = 'cobro';
    public const TIPO_PAGO  = 'pago';

    public const REGISTRADO = 'registrado';
    public const ANULADO    = 'anulado';

    protected $table = 'pagos';

    protected $fillable = [
        'empresa_id', 'venta_id', 'compra_id', 'caja_sesion_id', 'user_id', 'tipo', 'metodo_pago',
        'monto', 'moneda', 'tipo_cambio', 'referencia', 'fecha_pago', 'estado', 'observacion',
    ];

    protected function casts(): array
    {
        return ['monto' => 'float', 'tipo_cambio' => 'float', 'fecha_pago' => 'datetime'];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(CajaSesion::class, 'caja_sesion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
