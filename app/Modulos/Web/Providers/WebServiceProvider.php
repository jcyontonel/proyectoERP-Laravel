<?php

namespace App\Modulos\Web\Providers;

use App\Modulos\Erp\Events\VentaGuardada;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Nucleo\Providers\ProveedorModulo;
use App\Modulos\Web\Models\PedidoWeb;
use Illuminate\Support\Facades\Event;

/**
 * Página web de las empresas (producto "web"): tienda pública en Vue (contexto "web"), su API y el bloque
 * "Página web" del ERP (tienda, productos publicados, pedidos y zonas de envío).
 */
class WebServiceProvider extends ProveedorModulo
{
    public function boot(): void
    {
        // Venta registrada desde un pedido web: el pedido queda con su comprobante
        Event::listen(VentaGuardada::class, function (VentaGuardada $e) {
            if (blank($e->datos['pedido_id'] ?? null) || $e->venta->estado === Venta::BORRADOR) {
                return;
            }
            $pedido = PedidoWeb::query()->where('empresa_id', $e->venta->empresa_id)->lockForUpdate()->find($e->datos['pedido_id']);
            if ($pedido?->esConvertible()) {
                $pedido->update(['venta_id' => $e->venta->id, 'estado' => $pedido->estado === PedidoWeb::NUEVO ? PedidoWeb::CONFIRMADO : $pedido->estado]);
            }
        });

        $this->cargarRecursos('web');
        $this->rutasWeb('web.php');
        $this->rutasApi('api.php');
        $this->rutasWeb('publica.php', 'web', autenticado: false);
    }
}
