<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComprobanteSunat;
use App\Services\Facturacion\FacturacionElectronicaService;
use Illuminate\Http\Request;

/**
 * Avisos del facturador electrónico (comprobante aceptado tras un corte, baja terminada, error…).
 * Firma: X-Facturador-Firma: t={ts},v1={hmac_sha256("{ts}.{cuerpo}", FACTURADOR_WEBHOOK_SECRETO)}
 */
class FacturadorWebhookController extends Controller
{
    public function __invoke(Request $request, FacturacionElectronicaService $facturacion)
    {
        $secreto = (string) config('facturador.webhook_secreto');
        abort_if($secreto === '', 503, 'Webhook del facturador no configurado.');

        $cuerpo = $request->getContent();
        if (sscanf((string) $request->header('X-Facturador-Firma'), 't=%d,v1=%s', $t, $firma) !== 2
            || abs(time() - $t) > 300
            || ! hash_equals(hash_hmac('sha256', $t . '.' . $cuerpo, $secreto), $firma)) {
            return response()->json(['message' => 'Firma inválida.'], 401);
        }

        $c = $request->json('comprobante', []);
        $reg = filled($c['id'] ?? null) ? ComprobanteSunat::with('comprobante')->where('facturador_id', $c['id'])->first() : null;
        if (! $reg) {
            return response()->json(['message' => 'Comprobante desconocido; se ignora.'], 202);   // 2xx: que no se reintente
        }

        $facturacion->aplicar($reg, $c);

        return response()->noContent();
    }
}
