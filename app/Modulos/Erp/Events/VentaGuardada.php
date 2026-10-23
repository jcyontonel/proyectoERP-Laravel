<?php

namespace App\Modulos\Erp\Events;

use App\Modulos\Erp\Models\Venta;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se guardó una venta (registrada o borrador), dentro de su transacción. Otros módulos reaccionan sin que el
 * ERP los conozca: p. ej. la Página web marca el pedido de origen (datos['pedido_id']) como convertido.
 */
class VentaGuardada
{
    use Dispatchable;

    public function __construct(public readonly Venta $venta, public readonly array $datos)
    {
    }
}
