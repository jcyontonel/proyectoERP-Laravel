<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\PagoSuscripcionResource;
use App\Models\Empresa;
use App\Models\PagoSuscripcion;
use App\Services\Facturacion\ComprobanteSuscripcionService;
use App\Services\Facturacion\FacturacionElectronicaService;
use App\Services\Facturacion\FacturadorNoDisponible;
use App\Services\SuscripcionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Pagos de suscripciones de todas las empresas */
class PagoController extends AdminController
{
    /** Se resuelve en cada uso: el servicio es por petición (el controlador puede reutilizarse) */
    private function suscripciones(): SuscripcionService
    {
        return app(SuscripcionService::class);
    }

    public function index(Request $request)
    {
        $f = $request->validate([
            'estado'     => ['nullable', Rule::in([PagoSuscripcion::PENDIENTE, PagoSuscripcion::PAGADO, PagoSuscripcion::ANULADO])],
            'empresa_id' => ['nullable', 'integer'],
            'desde'      => ['nullable', 'date'],
            'hasta'      => ['nullable', 'date'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $query = PagoSuscripcion::query()
            ->when($f['estado'] ?? null, fn ($q, $e) => $q->where('estado', $e))
            ->when($f['empresa_id'] ?? null, fn ($q, $id) => $q->where('empresa_id', $id))
            // Fechas: de pago si está pagado; de creación si está pendiente o anulado
            ->when($f['desde'] ?? null, fn ($q, $d) => $q->whereRaw('COALESCE(fecha_pago, created_at) >= ?', [$d . ' 00:00:00']))
            ->when($f['hasta'] ?? null, fn ($q, $h) => $q->whereRaw('COALESCE(fecha_pago, created_at) <= ?', [$h . ' 23:59:59']));

        $totales = (clone $query)->selectRaw('estado, COUNT(*) AS cantidad, SUM(monto) AS total')->groupBy('estado')->get()
            ->mapWithKeys(fn ($r) => [$r->estado => ['cantidad' => (int) $r->cantidad, 'total' => round((float) $r->total, 2)]]);

        return PagoSuscripcionResource::collection(
            $query->with(self::RELACIONES)->latest('id')->paginate($f['por_pagina'] ?? 30)->withQueryString()
        )->additional(['totales' => $totales]);
    }

    /** Registra el pago del siguiente periodo de la suscripción vigente (o del cobro pendiente indicado) */
    public function store(Request $request, Empresa $empresa)
    {
        $d = $request->validate([
            'pago_id'    => ['nullable', 'integer'],
            'metodo'     => ['required', Rule::in(array_keys(config('suscripciones.metodos_pago')))],
            'referencia' => ['nullable', 'string', 'max:100'],
            'fecha_pago' => ['nullable', 'date', 'before_or_equal:today'],
            'notas'      => ['nullable', 'string', 'max:1000'],
            // Comprobante electrónico del pago: factura (por defecto, al RUC de la empresa), boleta o ninguno
            'comprobante' => ['nullable', Rule::in(['factura', 'boleta', 'ninguno'])],
        ]);

        $s = $this->suscripciones()->vigente($empresa->id);
        abort_unless($s, 422, 'La empresa no tiene una suscripción vigente: asígnale un plan.');

        $pago = $this->suscripciones()->registrarPago($s, $d, $this->operador($request));
        $pago->load(self::RELACIONES);

        return (new PagoSuscripcionResource($pago))->additional([
            'message' => 'Pago registrado. La suscripción queda vigente hasta el ' . $pago->periodo_hasta->format('d/m/Y') . '.'
                . FacturacionElectronicaService::aviso($pago->comprobante),
        ])->response()->setStatusCode(201);
    }

    public function show(PagoSuscripcion $pago)
    {
        return new PagoSuscripcionResource($pago->load(self::RELACIONES));
    }

    public function anular(Request $request, PagoSuscripcion $pago)
    {
        $d = $request->validate(['motivo' => ['nullable', 'string', 'max:255']]);
        $this->suscripciones()->anularPago($pago, $d['motivo'] ?? null);

        return new PagoSuscripcionResource($pago->refresh()->load(self::RELACIONES));
    }

    /** Emite el comprobante (si aún no tiene) o lo reenvía (pendiente, rechazado o con error) */
    public function comprobante(Request $request, PagoSuscripcion $pago, ComprobanteSuscripcionService $comprobantes, FacturacionElectronicaService $facturacion)
    {
        $d = $request->validate(['tipo' => ['nullable', Rule::in(['factura', 'boleta'])]]);
        abort_unless($comprobantes->activo(), 422, 'La facturación de suscripciones no está configurada: falta el facturador (FACTURADOR_URL/TOKEN) o la empresa emisora (SUSCRIPCIONES_EMISOR_EMPRESA_ID).');
        abort_unless($pago->esPagado(), 422, 'Solo los pagos registrados llevan comprobante.');

        $reg = $pago->comprobante()->first();
        $nota = $pago->notaCredito()->first();
        match (true) {
            $nota && $nota->esReenviable() => $facturacion->reenviar($nota),
            $reg && ($reg->esReenviable() || $reg->baja_estado === 'por_pedir') => $reg->baja_estado === 'por_pedir' ? $facturacion->anularRegistro($reg) : $facturacion->reenviar($reg),
            ! $reg => $comprobantes->emitir($pago, $d['tipo'] ?? null),
            default => null,
        };

        $pago->load(self::RELACIONES);

        return (new PagoSuscripcionResource($pago))->additional(['message' => 'Comprobante:' . FacturacionElectronicaService::aviso($pago->comprobante)]);
    }

    /** Archivos del comprobante (o de su nota de crédito): xml | cdr | nota-xml | nota-cdr */
    public function archivo(PagoSuscripcion $pago, string $cual, FacturacionElectronicaService $facturacion)
    {
        $nota = str_starts_with($cual, 'nota-');
        $reg = $nota ? $pago->notaCredito()->first() : $pago->comprobante()->first();
        $tipo = $nota ? substr($cual, 5) : $cual;
        abort_unless($reg, 404, 'El pago no tiene ese comprobante.');

        try {
            $resp = $facturacion->archivo($reg, $tipo);
        } catch (FacturadorNoDisponible $e) {
            abort(503, $e->getMessage());
        }
        abort_unless($resp, 404, $tipo === 'cdr' ? 'SUNAT aún no devolvió la constancia (CDR).' : 'El XML aún no está disponible.');

        return response($resp->body(), 200, [
            'Content-Type'        => $tipo === 'cdr' ? 'application/zip' : 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . ($tipo === 'cdr' ? 'R-' : '') . ($reg->nombre_archivo ?: $reg->numeroCompleto()) . ($tipo === 'cdr' ? '.zip' : '.xml') . '"',
        ]);
    }

    /** Representación impresa (HTML A4) del comprobante del pago */
    public function impreso(PagoSuscripcion $pago, ComprobanteSuscripcionService $comprobantes)
    {
        $pago->load(self::RELACIONES);
        abort_unless($pago->comprobante, 404, 'El pago no tiene comprobante.');

        return view('suscripciones.comprobante', ['pago' => $pago, 'emisor' => Empresa::find($pago->comprobante->empresa_id)]);
    }

    private const RELACIONES = ['empresa', 'suscripcion.plan', 'oferta', 'comprobante', 'notaCredito'];
}
