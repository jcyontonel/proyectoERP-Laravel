<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Venta;
use App\Models\Producto;
use App\Models\DetalleVenta;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class DetalleVentaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $ventas = Venta::all();

        foreach ($ventas as $venta) {
            $productos = Producto::where('empresa_id', $venta->empresa_id)->inRandomOrder()->limit(3)->get();
            $subtotal = 0;

            foreach ($productos as $producto) {
                $cantidad = $faker->numberBetween(1, 5);
                $precio = $producto->precio_unitario;
                $sub = $cantidad * $precio;

                DetalleVenta::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $producto->id,
                    'tipo_unidad_id' => $producto->tipo_unidad_id,
                    'descripcion' => $producto->nombre,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'subtotal' => $sub,
                    'total' => $sub ,
                ]);

                $subtotal += $sub;
            }

            $venta->update([
                'subtotal' => $subtotal,
                'total_impuestos' => 0,
                'total' => $subtotal,
            ]);
        }
    }
}
