<?php

namespace App\Modulos\Plataforma\Http\Controllers\Panel;

use App\Modulos\Nucleo\Http\Controllers\Controller;

use App\Modulos\Plataforma\Support\ApiAdmin;
use Illuminate\Http\Request;

/** Pagos de suscripciones de todas las empresas */
class PagoController extends Controller
{
    public function __construct(private ApiAdmin $erp)
    {
    }

    public function index(Request $request)
    {
        $r = $this->erp->get('pagos', array_filter($request->only(['estado', 'empresa_id', 'desde', 'hasta', 'page'])));

        return view('plataforma::pagos.index', [
            'pagos'     => $this->erp->paginar($r),
            'totales'   => $r['totales'] ?? [],
            'catalogos' => $this->erp->catalogos(),
        ]);
    }

    /** CSV con BOM UTF-8 (se abre directo en Excel) */
    public function export(Request $request)
    {
        $pagos = $this->erp->get('pagos', array_filter($request->only(['estado', 'empresa_id', 'desde', 'hasta'])) + ['por_pagina' => 1000])['data'];
        $metodos = $this->erp->catalogos()['metodos_pago'];
        $seguro = fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v;

        return response()->streamDownload(function () use ($pagos, $metodos, $seguro) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Empresa', 'Plan', 'Periodicidad', 'Periodo desde', 'Periodo hasta', 'Bruto', 'Descuento', 'Monto', 'Estado', 'Método', 'Referencia', 'Fecha de pago', 'Registrado por']);
            foreach ($pagos as $p) {
                fputcsv($out, [
                    $p['id'], $seguro($p['empresa'] ?? ''), $p['plan'] ?? '', $p['periodicidad'] ?? '', $p['periodo_desde'], $p['periodo_hasta'],
                    number_format($p['monto_bruto'], 2, '.', ''), number_format($p['descuento'], 2, '.', ''), number_format($p['monto'], 2, '.', ''),
                    $p['estado'], $metodos[$p['metodo']] ?? $p['metodo'], $seguro($p['referencia'] ?? ''),
                    $p['fecha_pago'] ? date('d/m/Y H:i', strtotime($p['fecha_pago'])) : '', $seguro($p['registrado_por'] ?? ''),
                ]);
            }
            fclose($out);
        }, 'pagos_suscripciones_' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Registra el pago del siguiente periodo (o del cobro pendiente indicado) */
    public function store(Request $request, int $empresa)
    {
        $r = $this->erp->post("empresas/{$empresa}/pagos", $request->only(['pago_id', 'metodo', 'referencia', 'fecha_pago', 'notas', 'comprobante']));

        return redirect()->route('plataforma.empresas.show', $empresa)->with('status', $r['message'] ?? 'Pago registrado.');
    }

    /** Emite el comprobante electrónico del pago (si no tiene) o lo reenvía a SUNAT */
    public function comprobante(Request $request, int $pago)
    {
        $r = $this->erp->post("pagos/{$pago}/comprobante", $request->only('tipo'));

        return back()->with('status', $r['message'] ?? 'Comprobante enviado.');
    }

    /** impreso (HTML para imprimir/PDF) | xml | cdr | nota-xml | nota-cdr */
    public function archivo(int $pago, string $cual)
    {
        $r = $this->erp->archivo("pagos/{$pago}/comprobante/{$cual}");

        return response($r->body(), 200, array_filter([
            'Content-Type'        => $r->header('Content-Type') ?: 'application/octet-stream',
            'Content-Disposition' => $r->header('Content-Disposition') ?: null,
        ]));
    }

    public function anular(Request $request, int $pago)
    {
        $this->erp->post("pagos/{$pago}/anular", $request->only('motivo'));

        return back()->with('status', 'Pago anulado. Si estaba pagado, la vigencia de la suscripción retrocedió.');
    }
}
