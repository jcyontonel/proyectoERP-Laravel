<?php

namespace App\Modulos\Facturacion\Services;

use App\Modulos\Facturacion\Http\Resources\DocumentoResource;
use App\Modulos\Facturacion\Models\Documento;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Avisos al sistema externo cuando cambia el estado de un comprobante que emitió por la API v1
 * (aceptado después de un corte, anulación terminada, error…). Cuerpo JSON firmado con HMAC-SHA256:
 *   X-Facturador-Firma: t={timestamp},v1={hmac_sha256("{timestamp}.{cuerpo}", secreto)}
 * Si el sistema no responde 2xx se reintenta con espera creciente; al final queda "fallido".
 */
class WebhookService
{
    public const EVENTO = 'comprobante.actualizado';

    public function programar(Documento $doc): void
    {
        if (! $this->url($doc)) {
            return;
        }
        $doc->update(['webhook_estado' => 'pendiente', 'webhook_intentos' => 0, 'webhook_proximo_at' => now(), 'webhook_ultimo_error' => null]);
        $this->entregar($doc);
    }

    /** Entrega los avisos pendientes; devuelve cuántos se entregaron */
    public function procesar(): int
    {
        $entregados = 0;
        Documento::with('sistema')->where('webhook_estado', 'pendiente')->where('webhook_proximo_at', '<=', now())
            ->limit(100)->get()
            ->each(function (Documento $d) use (&$entregados) {
                $entregados += (int) $this->entregar($d);
            });

        return $entregados;
    }

    public function entregar(Documento $doc): bool
    {
        $url = $this->url($doc);
        $cuerpo = json_encode([
            'evento'      => self::EVENTO,
            'fecha'       => now()->toIso8601String(),
            'comprobante' => (new DocumentoResource($doc->load('baja', 'empresa')))->resolve(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $t = time();
        $firma = hash_hmac('sha256', $t . '.' . $cuerpo, (string) $doc->sistema->webhook_secreto);

        $error = null;
        try {
            $resp = Http::timeout((int) config('facturacion.webhooks.timeout'))
                ->withOptions($this->opcionesTls($url))
                ->withHeaders(['X-Facturador-Evento' => self::EVENTO, 'X-Facturador-Firma' => "t={$t},v1={$firma}", 'X-Facturador-Id' => $doc->uuid])
                ->withBody($cuerpo, 'application/json')
                ->post($url);
            if ($resp->successful()) {
                $doc->update(['webhook_estado' => 'entregado', 'webhook_intentos' => $doc->webhook_intentos + 1, 'webhook_proximo_at' => null, 'webhook_ultimo_error' => null]);

                return true;
            }
            $error = 'HTTP ' . $resp->status();
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $esperas = config('facturacion.webhooks.esperas');
        $intentos = $doc->webhook_intentos + 1;
        $doc->update([
            'webhook_intentos'     => $intentos,
            'webhook_estado'       => $intentos > count($esperas) ? 'fallido' : 'pendiente',
            'webhook_proximo_at'   => $intentos > count($esperas) ? null : now()->addMinutes($esperas[$intentos - 1]),
            'webhook_ultimo_error' => mb_substr((string) $error, 0, 500),
        ]);

        return false;
    }

    /** Solo los documentos de un sistema externo con webhook */
    private function url(Documento $doc): ?string
    {
        $doc->loadMissing('sistema');

        return $doc->sistema ? ($doc->webhook_url ?: $doc->sistema->webhook_url) : null;
    }

    private function opcionesTls(string $url): array
    {
        $ca = config('facturacion.ca_bundle');
        $ruta = $ca ? (preg_match('/^([A-Za-z]:|\/)/', $ca) ? $ca : base_path($ca)) : null;

        return str_starts_with($url, 'https://') && $ruta && is_file($ruta) ? ['verify' => $ruta] : [];
    }
}
