<?php

namespace App\Modulos\Erp\Services;

use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompraExportService
{
    /** CSV con BOM UTF-8: Excel lo abre directamente con tildes correctas */
    public function csv(Builder $query): StreamedResponse
    {
        $compras = $query->with(['proveedor.tipoDocumento', 'almacen', 'usuario'])
            ->withSum(['pagos as pagado' => fn ($q) => $q->where('estado', 'registrado')], 'monto')
            ->limit((int) config('compras.export_max', 10000))
            ->get();

        return response()->streamDownload(function () use ($compras) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Comprobante', 'Tipo', 'Fecha', 'Proveedor', 'Documento', 'Almacén',
                'Base imponible', 'IGV', 'Total', 'Pagado', 'Saldo', 'Estado', 'Registrado por',
            ]);

            foreach ($compras as $c) {
                fputcsv($out, [
                    $c->numero_completo,
                    $c->tipo_etiqueta,
                    $c->fecha_emision->format('d/m/Y'),
                    $this->seguro($c->proveedor?->nombre ?? ''),
                    $c->proveedor?->documento_etiqueta ?? '',
                    $c->almacen?->nombre ?? '',
                    number_format($c->subtotal, 2, '.', ''),
                    number_format($c->total_impuestos, 2, '.', ''),
                    number_format($c->total, 2, '.', ''),
                    number_format($c->montoPagado(), 2, '.', ''),
                    number_format($c->esAnulada() ? 0 : $c->saldo(), 2, '.', ''),
                    ucfirst($c->estado),
                    $this->seguro($c->usuario?->name ?? ''),
                ]);
            }

            fclose($out);
        }, 'compras_' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Evita inyección de fórmulas al abrir el CSV en Excel */
    private function seguro(string $valor): string
    {
        return preg_match('/^[=+\-@\t\r]/', $valor) ? "'" . $valor : $valor;
    }
}
