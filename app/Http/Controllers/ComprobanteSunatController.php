<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Models\ComprobanteSunat;
use App\Services\Facturacion\FacturacionElectronicaService;
use App\Services\Facturacion\FacturadorNoDisponible;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Envíos a SUNAT (vía el facturador electrónico): estado, reenvío, XML y CDR */
class ComprobanteSunatController extends Controller
{
    use ResuelveEmpresa;

    public function __construct(private FacturacionElectronicaService $facturacion)
    {
    }

    /** Todos los comprobantes electrónicos; ?bajas=1 (ruta sunat.resumenes) muestra solo las anulaciones */
    public function index(Request $request, bool $bajas = false)
    {
        $f = $request->validate([
            'q'      => ['nullable', 'string', 'max:30'],
            'estado' => ['nullable', Rule::in(array_keys(ComprobanteSunat::ESTADOS))],
            'tipo'   => ['nullable', Rule::in(['01', '03', '07', '08'])],
            'desde'  => ['nullable', 'date'],
            'hasta'  => ['nullable', 'date'],
        ]);
        $base = ComprobanteSunat::query()->deEmpresa($this->empresaId($request));

        return view('sunat.index', [
            'bajas'        => $bajas,
            'comprobantes' => (clone $base)->filtrar($f)->when($bajas, fn ($q) => $q->whereNotNull('baja_estado'))
                ->with('comprobante')->orderByDesc('id')->paginate(25)->withQueryString(),
            'conteo'       => (clone $base)->selectRaw('estado, COUNT(*) AS cantidad')->groupBy('estado')->pluck('cantidad', 'estado'),
            'activo'       => $this->facturacion->activo(),
        ]);
    }

    public function resumenes(Request $request)
    {
        return $this->index($request, true);
    }

    public function reenviar(ComprobanteSunat $comprobante)
    {
        abort_unless($comprobante->esReenviable() || $comprobante->baja_estado === 'por_pedir', 422, 'Este comprobante no necesita reenviarse.');

        $reg = $comprobante->baja_estado === 'por_pedir'
            ? $this->facturacion->anularRegistro($comprobante)
            : $this->facturacion->reenviar($comprobante);

        return back()->with('status', "{$comprobante->numeroCompleto()}: " . mb_strtolower(($reg ?? $comprobante)->etiqueta()) . '.'
            . (($reg ?? $comprobante)->mensaje_respuesta ? ' ' . ($reg ?? $comprobante)->mensaje_respuesta : ''));
    }

    /** xml | cdr (se descarga del facturador) */
    public function archivo(ComprobanteSunat $comprobante, string $cual)
    {
        try {
            $resp = $this->facturacion->archivo($comprobante, $cual);
        } catch (FacturadorNoDisponible $e) {
            return back()->with('error', $e->getMessage());
        }
        if (! $resp) {
            return back()->with('error', $cual === 'cdr' ? 'SUNAT aún no devolvió la constancia (CDR).' : 'El XML aún no está disponible.');
        }

        $nombre = ($cual === 'cdr' ? 'R-' : '') . ($comprobante->nombre_archivo ?: $comprobante->numeroCompleto()) . ($cual === 'cdr' ? '.zip' : '.xml');

        return response($resp->body(), 200, [
            'Content-Type'        => $cual === 'cdr' ? 'application/zip' : 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }
}
