<?php

namespace Tests\Unit;

use App\Modulos\Facturacion\Sunat\Calculadora;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CalculadoraTest extends TestCase
{
    public function test_precios_con_igv_descuento_y_exonerado(): void
    {
        $r = Calculadora::calcular([
            ['descripcion' => 'A', 'cantidad' => 2, 'precio_unitario' => 150, 'descuento' => 30],   // 270 con IGV
            ['descripcion' => 'B', 'cantidad' => 3, 'precio_unitario' => 25.90],                    // 77.70
            ['descripcion' => 'C', 'cantidad' => 1, 'precio_unitario' => 40, 'afectacion' => '20'],
        ], 18, true);

        [$a, $b, $c] = $r['items'];
        $this->assertEquals(228.81, $a['valor_venta']);
        $this->assertEquals(41.19, $a['igv']);
        $this->assertEquals(254.24, $a['valor_bruto']);          // 2 × 127.1186…
        $this->assertEquals(25.43, $a['descuento']);             // descuento sin IGV
        $this->assertEqualsWithDelta($a['valor_bruto'] - $a['descuento'], $a['valor_venta'], 0.001);
        $this->assertEqualsWithDelta(135.0, $a['precio_unitario'], 0.0001);   // (base + IGV) / cantidad
        $this->assertEquals(65.85, $b['valor_venta']);
        $this->assertEquals(40.0, $c['valor_venta']);
        $this->assertEquals(0.0, $c['igv']);

        $t = $r['totales'];
        $this->assertEquals(294.66, $t['gravadas']);
        $this->assertEquals(40.0, $t['exoneradas']);
        $this->assertEquals(53.04, $t['igv']);
        $this->assertEquals(387.70, $t['total']);                // 270 + 77.70 + 40
    }

    public function test_precios_sin_igv(): void
    {
        $r = Calculadora::calcular([['descripcion' => 'A', 'cantidad' => 4, 'precio_unitario' => 25, 'descuento' => 10]], 18, false);

        $this->assertEquals(90.0, $r['items'][0]['valor_venta']);
        $this->assertEquals(16.2, $r['items'][0]['igv']);
        $this->assertEquals(10.0, $r['items'][0]['descuento']);
        $this->assertEquals(106.2, $r['totales']['total']);
    }

    public function test_respeta_montos_del_sistema_si_cuadran(): void
    {
        $r = Calculadora::calcular([['descripcion' => 'A', 'cantidad' => 3, 'precio_unitario' => 3.33, 'valor_venta' => 8.46, 'igv' => 1.53]], 18, true);
        $this->assertEquals(8.46, $r['items'][0]['valor_venta']);
        $this->assertEquals(9.99, $r['totales']['total']);

        $this->expectException(ValidationException::class);
        Calculadora::calcular([['descripcion' => 'A', 'cantidad' => 3, 'precio_unitario' => 3.33, 'valor_venta' => 20, 'igv' => 1.53]], 18, true);
    }
}
