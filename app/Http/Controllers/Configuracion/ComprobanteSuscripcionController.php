<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\PagoSuscripcion;
use App\Services\Facturacion\FacturacionElectronicaService;
use App\Services\Facturacion\FacturadorNoDisponible;
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
            return view('suscripciones.comprobante', ['pago' => $pago, 'emisor' => Empresa::find($pago->comprobante->empresa_id)]);
        }

        try {
            $resp = $facturacion->archivo($pago->comprobante, $cual);
        } catch (FacturadorNoDisponible $e) {
            return back()->with('error', 'No se pudo descargar: ' . $e->getMessage());
        }
        if (! $resp) {
            return back()->with('error', $cual === 'cdr' ? 'SUNAT aún no devolvió la constancia (CDR).' : 'El XML aún no está disponible.');
        }

        $nombre = ($cual === 'cdr' ? 'R-' : '') . ($pago->comprobante->nombre_archivo ?: $pago->comprobante->numeroCompleto()) . ($cual === 'cdr' ? '.zip' : '.xml');

        return response($resp->body(), 200, [
            'Content-Type'        => $cual === 'cdr' ? 'application/zip' : 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }
}
