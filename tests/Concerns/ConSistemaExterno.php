<?php

namespace Tests\Concerns;

use App\Modulos\Facturacion\Models\SistemaExterno;
use App\Modulos\Nucleo\Models\Empresa;

/** Sistema externo con token para la API v1 de comprobantes, emitiendo por una empresa en beta */
trait ConSistemaExterno
{
    use ConEmpresa;

    protected SistemaExterno $sistema;

    protected string $token;

    protected Empresa $empresa;

    protected function conSistema(string $ruc = '20123456789', ?string $webhook = 'https://erp.test/webhook'): void
    {
        $this->sembrarCatalogos();
        $this->empresa = $this->crearEmpresa([
            'ruc' => $ruc, 'razon_social' => 'MASCOTAS SAC', 'direccion' => 'AV. LIMA 123', 'ubigeo' => '150101',
            'departamento' => 'LIMA', 'provincia' => 'LIMA', 'distrito' => 'LIMA', 'sunat_modo' => 'beta',
        ]);
        [$this->sistema, $this->token] = SistemaExterno::registrar($this->empresa, 'ERP de prueba', $webhook);
    }

    protected function api(): static
    {
        return $this->withToken($this->token)->withHeader('Accept', 'application/json');
    }

    /** Factura válida de S/ 118 (2 × 59 con IGV) */
    protected function factura(array $extra = []): array
    {
        return array_replace_recursive([
            'emisor' => $this->empresa->ruc, 'tipo' => '01', 'serie' => 'F001', 'numero' => 1,
            'cliente' => ['tipo_doc' => '6', 'numero' => '20100070970', 'nombre' => 'SUPERMERCADOS PERUANOS SA'],
            'items' => [['codigo' => 'P1', 'descripcion' => 'Alimento', 'cantidad' => 2, 'precio_unitario' => 59]],
            'referencia' => 'venta-1',
        ], $extra);
    }
}
