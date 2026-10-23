<?php

namespace App\Modulos\Erp\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VentaExportService
{
    /** CSV con BOM UTF-8: Excel lo abre directamente con tildes correctas */
    public function csv(Builder $query): StreamedResponse
    {
        $metodos = config('ventas.metodos_pago');
        $ventas  = $query->with(['cliente.tipoDocumento', 'usuario'])
            ->limit((int) config('ventas.export_max', 10000))
            ->get();

        $nombre = 'ventas_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($ventas, $metodos) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'N° Comprobante', 'Fecha', 'Hora', 'Tipo', 'Cliente', 'Documento', 'Método de pago',
                'Base imponible', 'IGV', 'Descuento', 'Total', 'Estado', 'Vendedor',
            ]);

            foreach ($ventas as $v) {
                fputcsv($out, [
                    $v->numero_completo,
                    $v->fecha_emision->format('d/m/Y'),
                    $v->fecha_emision->format('H:i'),
                    $v->tipo_etiqueta,
                    $this->seguro($v->cliente?->nombre_completo ?: 'Público general'),
                    $v->cliente?->documento_etiqueta ?? '',
                    $metodos[$v->metodo_pago] ?? Str::headline((string) $v->metodo_pago),
                    number_format($v->subtotal, 2, '.', ''),
                    number_format($v->total_impuestos, 2, '.', ''),
                    number_format($v->descuento, 2, '.', ''),
                    number_format($v->total, 2, '.', ''),
                    ucfirst($v->estado),
                    $this->seguro($v->usuario?->name ?? ''),
                ]);
            }

            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Evita inyección de fórmulas al abrir el CSV en Excel */
    private function seguro(string $valor): string
    {
        return preg_match('/^[=+\-@\t\r]/', $valor) ? "'" . $valor : $valor;
    }
}
