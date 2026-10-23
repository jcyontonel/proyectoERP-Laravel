<?php

namespace App\Modulos\Erp\Models;

use App\Modulos\Nucleo\Models\User;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use App\Modulos\Nucleo\Models\Concerns\EsDeEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Ingreso o egreso manual de un turno de caja (gastos, retiros, fondo adicional…) */
class MovimientoCaja extends Model
{
    use Auditable, EsDeEmpresa, SoftDeletes;

    public const INGRESO = 'ingreso';
    public const EGRESO  = 'egreso';

    public const REGISTRADO = 'registrado';
    public const ANULADO    = 'anulado';

    protected $table = 'movimientos_caja';

    protected $fillable = [
        'empresa_id', 'caja_sesion_id', 'pago_id', 'user_id', 'tipo', 'concepto',
        'metodo_pago', 'monto', 'fecha_movimiento', 'estado', 'observacion',
    ];

    protected function casts(): array
    {
        return ['monto' => 'float', 'fecha_movimiento' => 'datetime'];
    }

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(CajaSesion::class, 'caja_sesion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function esIngreso(): bool
    {
        return $this->tipo === self::INGRESO;
    }

    public function esAnulado(): bool
    {
        return $this->estado === self::ANULADO;
    }
}
