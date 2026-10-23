<?php

namespace App\Modulos\Plataforma\Http\Controllers\Panel;

use App\Modulos\Nucleo\Http\Controllers\Controller;

use App\Modulos\Plataforma\Support\ApiAdmin;
use Illuminate\Http\Request;

/** Plan de cada empresa: asignar o cambiar, suspender, reactivar, cancelar y cobrar */
class SuscripcionController extends Controller
{
    public function __construct(private ApiAdmin $erp)
    {
    }

    public function store(Request $request, int $empresa)
    {
        $this->erp->post("empresas/{$empresa}/suscripcion", $request->only(['plan_id', 'periodicidad', 'oferta', 'metodo', 'referencia', 'notas', 'comprobante']) + [
            'prueba'                => $request->boolean('prueba'),
            'pagado'                => $request->boolean('pagado'),
            'renovacion_automatica' => $request->boolean('renovacion_automatica', true),
        ]);

        return $this->volver($empresa, 'Plan asignado.');
    }

    public function update(Request $request, int $empresa, int $suscripcion)
    {
        $this->erp->put("suscripciones/{$suscripcion}", ['renovacion_automatica' => $request->boolean('renovacion_automatica'), 'notas' => $request->input('notas')]);

        return $this->volver($empresa, 'Suscripción actualizada.');
    }

    public function suspender(Request $request, int $empresa, int $suscripcion)
    {
        $this->erp->post("suscripciones/{$suscripcion}/suspender", $request->only('motivo'));

        return $this->volver($empresa, 'Suscripción suspendida: la empresa ya no puede emitir comprobantes.');
    }

    public function reactivar(int $empresa, int $suscripcion)
    {
        $this->erp->post("suscripciones/{$suscripcion}/reactivar");

        return $this->volver($empresa, 'Suscripción reactivada.');
    }

    public function cancelar(Request $request, int $empresa, int $suscripcion)
    {
        $this->erp->post("suscripciones/{$suscripcion}/cancelar", $request->only('motivo'));

        return $this->volver($empresa, 'Suscripción cancelada.');
    }

    /** Genera el cobro pendiente del siguiente periodo (si no existe) */
    public function cobro(int $empresa, int $suscripcion)
    {
        $pago = $this->erp->post("suscripciones/{$suscripcion}/cobro")['data'];

        return $this->volver($empresa, 'Cobro pendiente de S/ ' . number_format($pago['monto'], 2) . ' por el periodo ' . date('d/m/Y', strtotime($pago['periodo_desde'])) . ' – ' . date('d/m/Y', strtotime($pago['periodo_hasta'])) . '.');
    }

    private function volver(int $empresa, string $mensaje)
    {
        return redirect()->route('plataforma.empresas.show', $empresa)->with('status', $mensaje);
    }
}
