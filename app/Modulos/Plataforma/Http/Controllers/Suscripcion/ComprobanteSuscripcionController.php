<?php

namespace App\Modulos\Plataforma\Http\Controllers\Suscripcion;

use App\Modulos\Facturacion\Http\Controllers\ComprobanteSunatController;
use App\Modulos\Facturacion\Services\FacturacionElectronicaService;
use App\Modulos\Nucleo\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Modulos\Nucleo\Http\Controllers\Controller;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Plataforma\Models\PagoSuscripcion;
use Illuminate\Http\Request;

/** La empresa cliente ve y descarga los comprobantes de sus pagos de suscripción */
class ComprobanteSuscripcionController extends Controller
{
    use ResuelveEmpresa;

    /** impreso | xml | cdr */
    public function __invoke(Request $request, PagoSuscripcion $pago, string $cual, FacturacionElectronicaService $facturacion)
    {
        abort_unless($pago->empresa_id === $this->empresaId($request), 404);
        $pago->load('empresa', 'suscripcion.plan', 'oferta', 'comprobante', 'notaCredito');
        abort_unless($pago->comprobante, 404);

        if ($cual === 'impreso') {
            return view('plataforma::suscripciones.comprobante', ['pago' => $pago, 'emisor' => Empresa::find($pago->comprobante->empresa_id)]);
        }

        $contenido = $facturacion->archivo($pago->comprobante, $cual);
        if ($contenido === null) {
            return back()->with('error', $cual === 'cdr' ? 'SUNAT aún no devolvió la constancia (CDR).' : 'El XML aún no está disponible.');
        }

        return ComprobanteSunatController::descarga($pago->comprobante, $cual, $contenido);
    }
}
