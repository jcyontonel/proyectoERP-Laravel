<?php

namespace App\Modulos\Erp\Services;

use App\Modulos\Erp\Models\Correlativo;

/**
 * Numeración de documentos internos (ajustes, transferencias, devoluciones) con la tabla correlativos.
 * Si la empresa aún no tiene la serie del módulo, se crea la serie por defecto.
 */
class CorrelativoService
{
    /** modulo => [tipo, descripción, serie por defecto] */
    public const DEFECTOS = [
        'ajuste'        => ['AJ', 'Ajuste de inventario', 'AJ01'],
        'transferencia' => ['TR', 'Transferencia', 'TR01'],
        'devolucion'    => ['DV', 'Devolución', 'DV01'],
        'fraccionamiento' => ['FR', 'Fraccionamiento', 'FR01'],
        'cotizacion'      => ['CT', 'Cotización', 'CT01'],
        'orden_compra'    => ['OC', 'Orden de compra', 'OC01'],
    ];

    /**
     * Avanza el correlativo del módulo y devuelve [serie, número]. Llamar dentro de una transacción.
     *
     * @return array{0: string, 1: int}
     */
    public function emitir(int $empresaId, string $modulo): array
    {
        $corr = Correlativo::query()
            ->deEmpresa($empresaId)
            ->where('modulo', $modulo)
            ->activo()
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (! $corr) {
            [$tipo, $descripcion, $serie] = self::DEFECTOS[$modulo];

            $corr = Correlativo::firstOrCreate(
                ['empresa_id' => $empresaId, 'tipo' => $tipo, 'serie' => $serie],
                ['modulo' => $modulo, 'destipo' => $descripcion, 'numero' => 0, 'activo' => true],
            );
            $corr = Correlativo::query()->lockForUpdate()->findOrFail($corr->id);
        }

        $corr->emitir();

        return [$corr->serie, (int) $corr->numero];
    }
}
