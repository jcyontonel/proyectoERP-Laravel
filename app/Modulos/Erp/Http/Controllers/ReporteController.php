<?php

namespace App\Modulos\Erp\Http\Controllers;

use App\Modulos\Nucleo\Http\Controllers\Controller;

use App\Modulos\Nucleo\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Modulos\Erp\Http\Requests\FiltrarReporteRequest;
use App\Modulos\Erp\Models\Almacen;
use App\Modulos\Catalogo\Models\Categoria;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Erp\Services\Reportes\ReporteService;
use App\Modulos\Suscripciones\Support\Modulos;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Catálogo de reportes, vista genérica (indicadores + gráfico + tabla) y exportación CSV */
class ReporteController extends Controller
{
    use ResuelveEmpresa;

    public function __construct(private ReporteService $reportes)
    {
    }

    public function index(Request $request)
    {
        $empresaId = $this->empresaId($request);

        return view('reportes.index', [
            // Solo los reportes que incluye el plan de la empresa
            'grupos' => collect(ReporteService::CATALOGO)->filter(fn ($r, $clave) => Modulos::rutaIncluida("reportes.{$clave}", $empresaId))->map(fn ($r, $clave) => [
                'clave' => $clave, 'titulo' => $r[0], 'descripcion' => $r[1], 'icono' => $r[2], 'grupo' => $r[3],
            ])->groupBy('grupo'),
        ]);
    }

    public function show(FiltrarReporteRequest $request, string $reporte)
    {
        $empresaId = $this->empresaId($request);
        [$titulo, $descripcion, $icono, $grupo, $filtros] = ReporteService::CATALOGO[$reporte];

        return view('reportes.reporte', [
            'clave'       => $reporte,
            'titulo'      => $titulo,
            'descripcion' => $descripcion,
            'grupo'       => $grupo,
            'filtros'     => $filtros,
            'datos'       => $this->reportes->generar($reporte, $empresaId, $request->validated()),
            'opciones'    => $this->opciones($empresaId, $filtros),
        ]);
    }

    /** CSV (UTF-8 con BOM, separado por ";" para Excel en español) */
    public function exportar(FiltrarReporteRequest $request, string $reporte): StreamedResponse
    {
        $datos = $this->reportes->generar($reporte, $this->empresaId($request), $request->validated());

        return response()->streamDownload(function () use ($datos) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, array_column($datos['columnas'], 1), ';');

            foreach ($datos['filas'] as $fila) {
                fputcsv($salida, array_map(fn ($c) => $fila[$c[0]] ?? '', $datos['columnas']), ';');
            }
            if (! empty($datos['totales'])) {
                fputcsv($salida, array_map(fn ($c) => $datos['totales'][$c[0]] ?? '', $datos['columnas']), ';');
            }
            fclose($salida);
        }, Str::slug($reporte) . '-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function opciones(int $empresaId, array $filtros): array
    {
        return [
            'almacenes'  => in_array('almacen', $filtros, true) ? Almacen::query()->deEmpresa($empresaId)->orderBy('nombre')->get(['id', 'nombre']) : collect(),
            'categorias' => in_array('categoria', $filtros, true) ? Categoria::query()->deEmpresa($empresaId)->orderBy('nombre')->get(['id', 'nombre']) : collect(),
            'vendedores' => in_array('vendedor', $filtros, true)
                ? User::query()->whereHas('empresas', fn ($q) => $q->whereKey($empresaId))->orderBy('name')->get(['id', 'name', 'apellido'])
                : collect(),
        ];
    }
}
