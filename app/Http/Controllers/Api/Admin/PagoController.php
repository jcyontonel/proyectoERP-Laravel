<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\PagoSuscripcionResource;
use App\Models\Empresa;
use App\Models\PagoSuscripcion;
use App\Services\SuscripcionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Pagos de suscripciones de todas las empresas */
class PagoController extends AdminController
{
    public function __construct(private SuscripcionService $suscripciones)
    {
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
            $query->with('empresa', 'suscripcion.plan', 'oferta')->latest('id')->paginate($f['por_pagina'] ?? 30)->withQueryString()
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
        ]);

        $s = $this->suscripciones->vigente($empresa->id);
        abort_unless($s, 422, 'La empresa no tiene una suscripción vigente: asígnale un plan.');

        $pago = $this->suscripciones->registrarPago($s, $d, $this->operador($request));

        return (new PagoSuscripcionResource($pago->load('empresa', 'suscripcion.plan', 'oferta')))->additional([
            'message' => 'Pago registrado. La suscripción queda vigente hasta el ' . $pago->periodo_hasta->format('d/m/Y') . '.',
        ])->response()->setStatusCode(201);
    }

    public function anular(Request $request, PagoSuscripcion $pago)
    {
        $d = $request->validate(['motivo' => ['nullable', 'string', 'max:255']]);
        $this->suscripciones->anularPago($pago, $d['motivo'] ?? null);

        return new PagoSuscripcionResource($pago->refresh()->load('empresa', 'suscripcion.plan', 'oferta'));
    }
}
