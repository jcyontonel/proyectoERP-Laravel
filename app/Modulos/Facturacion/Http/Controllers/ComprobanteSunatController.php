<?php

namespace App\Modulos\Facturacion\Http\Controllers;

use App\Modulos\Facturacion\Models\Documento;
use App\Modulos\Facturacion\Services\FacturacionElectronicaService;
use App\Modulos\Nucleo\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Modulos\Nucleo\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Envíos a SUNAT de la empresa: estado, reenvío, XML y CDR */
class ComprobanteSunatController extends Controller
{
    use ResuelveEmpresa;

    public function __construct(private FacturacionElectronicaService $facturacion)
    {
    }

    /** Todos los comprobantes electrónicos; la ruta sunat.resumenes muestra solo los que tienen baja */
    public function index(Request $request, bool $bajas = false)
    {
        $f = $request->validate([
            'q'      => ['nullable', 'string', 'max:30'],
            'estado' => ['nullable', Rule::in(array_keys(Documento::ESTADOS))],
            'tipo'   => ['nullable', Rule::in(Documento::COMPROBANTES)],
            'desde'  => ['nullable', 'date'],
            'hasta'  => ['nullable', 'date'],
        ]);
        $base = Documento::query()->deEmpresa($this->empresaId($request))->comprobantes();

        return view('sunat.index', [
            'bajas'        => $bajas,
            'comprobantes' => (clone $base)->filtrar($f)->when($bajas, fn ($q) => $q->whereNotNull('baja_id'))
                ->with('comprobante', 'baja')->orderByDesc('id')->paginate(25)->withQueryString(),
            'conteo'       => (clone $base)->selectRaw('estado, COUNT(*) AS cantidad')->groupBy('estado')->pluck('cantidad', 'estado'),
            'activo'       => $this->facturacion->activo(),
        ]);
    }

    public function resumenes(Request $request)
    {
        return $this->index($request, true);
    }

    public function reenviar(Documento $comprobante)
    {
        abort_unless($comprobante->esReenviable(), 422, 'Este comprobante no necesita reenviarse.');

        $doc = $this->facturacion->reenviar($comprobante);

        return back()->with('status', "{$doc->numeroCompleto()}: " . mb_strtolower($doc->etiqueta()) . '.' . ($doc->mensaje() ? ' ' . $doc->mensaje() : ''));
    }

    /** xml | cdr */
    public function archivo(Documento $comprobante, string $cual)
    {
        $contenido = $this->facturacion->archivo($comprobante, $cual);
        if ($contenido === null) {
            return back()->with('error', $cual === 'cdr' ? 'SUNAT aún no devolvió la constancia (CDR).' : 'El XML aún no está disponible.');
        }

        return self::descarga($comprobante, $cual, $contenido);
    }

    /** Respuesta de descarga del XML firmado o del CDR (zip) */
    public static function descarga(Documento $doc, string $cual, string $contenido)
    {
        $nombre = ($cual === 'cdr' ? 'R-' : '') . ($doc->nombre_archivo ?: $doc->numeroCompleto()) . ($cual === 'cdr' ? '.zip' : '.xml');

        return response($contenido, 200, [
            'Content-Type'        => $cual === 'cdr' ? 'application/zip' : 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }
}
