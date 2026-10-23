<?php

namespace App\Services\Facturacion;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP del facturador electrónico (API v1). Devuelve la Response tal cual; los fallos de red
 * se lanzan como FacturadorNoDisponible para que el ERP deje el comprobante pendiente y lo reintente.
 */
class FacturadorCliente
{
    public function configurado(): bool
    {
        return filled(config('facturador.url')) && filled(config('facturador.token'));
    }

    public function ping(): Response
    {
        return $this->llamar(fn (PendingRequest $h) => $h->get('/ping'));
    }

    public function emisor(string $ruc, array $datos): Response
    {
        return $this->llamar(fn (PendingRequest $h) => $h->put("/emisores/{$ruc}", $datos));
    }

    public function emitir(array $comprobante): Response
    {
        return $this->llamar(fn (PendingRequest $h) => $h->post('/comprobantes', $comprobante));
    }

    public function consultar(string $id): Response
    {
        return $this->llamar(fn (PendingRequest $h) => $h->get("/comprobantes/{$id}"));
    }

    public function reenviar(string $id): Response
    {
        return $this->llamar(fn (PendingRequest $h) => $h->post("/comprobantes/{$id}/reenviar"));
    }

    public function anular(string $id, string $motivo): Response
    {
        return $this->llamar(fn (PendingRequest $h) => $h->post("/comprobantes/{$id}/anular", ['motivo' => $motivo]));
    }

    /** xml | cdr */
    public function archivo(string $id, string $cual): Response
    {
        return $this->llamar(fn (PendingRequest $h) => $h->accept('*/*')->get("/comprobantes/{$id}/{$cual}"));
    }

    private function llamar(callable $fn): Response
    {
        if (! $this->configurado()) {
            throw new FacturadorNoDisponible('La facturación electrónica no está configurada (FACTURADOR_URL y FACTURADOR_TOKEN).');
        }

        $http = Http::baseUrl(rtrim(config('facturador.url'), '/'))
            ->withToken(config('facturador.token'))
            ->acceptJson()
            ->connectTimeout(config('facturador.timeout_conexion'))
            ->timeout(config('facturador.timeout'));
        $ca = config('facturador.ca_bundle');
        if ($ca && str_starts_with((string) config('facturador.url'), 'https://')) {
            $http->withOptions(['verify' => preg_match('/^([A-Za-z]:|\/)/', $ca) ? $ca : base_path($ca)]);
        }

        try {
            $resp = $fn($http);
        } catch (ConnectionException $e) {
            throw new FacturadorNoDisponible('No hay conexión con el facturador: ' . $e->getMessage(), previous: $e);
        }

        if ($resp->serverError() || $resp->status() === 401 || $resp->status() === 429) {
            throw new FacturadorNoDisponible('El facturador respondió HTTP ' . $resp->status() . ': ' . ($resp->json('message') ?? 'sin detalle'));
        }

        return $resp;
    }
}
