<?php

namespace App\Modulos\Facturacion\Http\Controllers\Api\V1;

use App\Modulos\Facturacion\Contracts\EmisorComprobantes;
use App\Modulos\Facturacion\Http\Controllers\ComprobanteSunatController;
use App\Modulos\Facturacion\Http\Requests\ComprobanteRequest;
use App\Modulos\Facturacion\Http\Resources\DocumentoResource;
use App\Modulos\Facturacion\Models\Documento;
use App\Modulos\Facturacion\Models\SistemaExterno;
use App\Modulos\Nucleo\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * API v1 para sistemas de fuera de la plataforma: cada token emite por UNA empresa.
 * Al registrar se envía a SUNAT en el momento (salvo "asincrono": true); si no hay conexión responde
 * estado "pendiente" y el timer lo reenvía y avisa por webhook. Solo ve los comprobantes de su sistema.
 */
class ComprobanteController extends Controller
{
    public function __construct(private EmisorComprobantes $emisor)
    {
    }

    public function ping(Request $request)
    {
        $s = $this->sistema($request)->load('empresa');
        $doc = (new Documento())->setRelation('empresa', $s->empresa);

        return response()->json([
            'sistema'  => $s->nombre,
            'emisores' => [['ruc' => $s->empresa->ruc, 'razon_social' => $s->empresa->razon_social, 'modo' => $doc->emisor()->modo()]],
            'hora'     => now()->toIso8601String(),
        ]);
    }

    public function index(Request $request)
    {
        $f = $request->validate([
            'tipo'       => ['nullable', Rule::in(array_keys(Documento::TIPOS))],
            'estado'     => ['nullable', 'string', 'max:12'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'desde'      => ['nullable', 'date'],
            'hasta'      => ['nullable', 'date'],
            'por_pagina' => ['nullable', 'integer', 'between:1,200'],
        ]);

        $docs = $this->delSistema($request)->with(['empresa', 'baja'])
            ->when($f['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($f['referencia'] ?? null, fn ($q, $v) => $q->where('referencia', $v))
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha_emision', '>=', $v))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha_emision', '<=', $v))
            ->orderByDesc('fecha_emision')->orderByDesc('id')
            ->paginate($f['por_pagina'] ?? 50);

        return DocumentoResource::collection($docs);
    }

    public function store(ComprobanteRequest $request)
    {
        $sistema = $this->sistema($request)->load('empresa');
        $d = $request->validated();
        if (filled($d['emisor'] ?? null) && $d['emisor'] !== $sistema->empresa->ruc) {
            throw ValidationException::withMessages(['emisor' => "El token de este sistema emite solo por el RUC {$sistema->empresa->ruc}."]);
        }

        [$doc, $nuevo] = $this->emisor->registrar($sistema->empresa, $d, null, $sistema);

        if ($nuevo && $doc->estado === Documento::PENDIENTE && ! $request->boolean('asincrono')) {
            $this->emisor->enviar($doc, avisar: false);   // el estado va en esta misma respuesta
        }

        return (new DocumentoResource($doc->refresh()->load('baja')))->response()
            ->setStatusCode($nuevo ? ($request->boolean('asincrono') ? 202 : 201) : 200)
            ->header('X-Idempotente', $nuevo ? 'no' : 'si');
    }

    public function show(Request $request, string $id)
    {
        return new DocumentoResource($this->buscar($request, $id)->load('baja'));
    }

    public function xml(Request $request, string $id)
    {
        $doc = $this->buscar($request, $id);
        $contenido = $this->emisor->archivo($doc, 'xml');
        abort_if($contenido === null, 404, 'El XML aún no se generó.');

        return ComprobanteSunatController::descarga($doc, 'xml', $contenido);
    }

    public function cdr(Request $request, string $id)
    {
        $doc = $this->buscar($request, $id);
        $contenido = $this->emisor->archivo($doc, 'cdr');
        abort_if($contenido === null, 404, 'SUNAT aún no devolvió el CDR.');

        return ComprobanteSunatController::descarga($doc, 'cdr', $contenido);
    }

    /** Reenvía ahora (no espera al timer) un comprobante pendiente o con error */
    public function reenviar(Request $request, string $id)
    {
        $doc = $this->buscar($request, $id);
        abort_unless(in_array($doc->estado, [Documento::PENDIENTE, Documento::ERROR], true), 422, "No se reenvía un comprobante en estado {$doc->estado}.");

        if ($doc->estado === Documento::ERROR) {
            $doc->update(['estado' => Documento::PENDIENTE]);
        }
        $this->emisor->enviar($doc, avisar: false);

        return new DocumentoResource($doc->refresh()->load('baja'));
    }

    /** Anula: comunicación de baja (facturas) o resumen diario (boletas). Responde 202: el resultado llega por webhook */
    public function anular(Request $request, string $id)
    {
        $motivo = $request->validate(['motivo' => ['required', 'string', 'max:100']])['motivo'];
        $doc = $this->buscar($request, $id);
        $this->emisor->anular($doc, $motivo);

        return (new DocumentoResource($doc->refresh()->load('baja')))->response()->setStatusCode(202);
    }

    /** Consulta el CDR en SUNAT (solo producción) */
    public function consultar(Request $request, string $id)
    {
        $doc = $this->buscar($request, $id);
        $r = $this->emisor->consultarCdr($doc);

        return (new DocumentoResource($doc->refresh()->load('baja')))->additional(['consulta' => ['resultado' => $r->resultado, 'codigo' => $r->codigo, 'descripcion' => $r->descripcion]]);
    }

    /* ───────────────────────── Privado ───────────────────────── */

    private function delSistema(Request $request)
    {
        $s = $this->sistema($request);

        return Documento::query()->where('empresa_id', $s->empresa_id)->where('sistema_id', $s->id);
    }

    private function buscar(Request $request, string $id): Documento
    {
        return $this->delSistema($request)->where('uuid', $id)->firstOr(fn () => abort(404, 'Comprobante no encontrado.'));
    }

    private function sistema(Request $request): SistemaExterno
    {
        return $request->attributes->get('sistema');
    }
}
