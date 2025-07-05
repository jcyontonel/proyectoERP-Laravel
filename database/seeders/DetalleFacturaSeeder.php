<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Factura;
use App\Models\Producto;
use App\Models\DetalleFactura;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class DetalleFacturaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $facturas = Factura::all();

        foreach ($facturas as $factura) {
            $productos = Producto::where('empresa_id', $factura->empresa_id)->inRandomOrder()->limit(3)->get();
            $subtotal = 0;
            $totalImpuestos = 0;

            foreach ($productos as $producto) {
                $cantidad = $faker->numberBetween(1, 5);
                $precio = $producto->precio_unitario;
                $sub = $cantidad * $precio;
                $impuesto = $producto->impuesto;
                $porcentaje = $impuesto ? $impuesto->porcentaje : 0;
                $igv = $sub * ($porcentaje / 100);

                DetalleFactura::create([
                    'factura_id' => $factura->id,
                    'producto_id' => $producto->id,
                    'tipo_unidad_id' => $producto->tipo_unidad_id,
                    'impuesto_id' => $producto->impuesto_id,
                    'descripcion' => $producto->nombre,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'subtotal' => $sub,
                    'total_impuestos' => $igv,
                    'total' => $sub + $igv,
                ]);

                $subtotal += $sub;
                $totalImpuestos += $igv;
            }

            $factura->update([
                'subtotal' => $subtotal,
                'total_impuestos' => $totalImpuestos,
                'total' => $subtotal + $totalImpuestos,
            ]);
        }
    }
}
