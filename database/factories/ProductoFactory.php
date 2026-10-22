<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\TipoUnidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * El stock no va aquí: se registra con InventarioService (almacen_producto + kardex).
 *
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    public function definition(): array
    {
        $precio = fake()->randomFloat(2, 3, 100);

        return [
            'empresa_id' => Empresa::factory(),
            // La categoría siempre es de la misma empresa que el producto
            'categoria_id' => fn (array $a) => Categoria::factory()->state(['empresa_id' => $a['empresa_id']]),
            'tipo_unidad_id' => fn () => TipoUnidad::where('codigo', 'UND')->value('id') ?? TipoUnidad::factory(),
            'codigo' => fake()->unique()->ean13(),
            'nombre' => ucfirst(fake()->unique()->words(4, true)),
            'descripcion' => fake()->sentence(10),
            'precio_unitario' => $precio,
            'costo_unitario' => round($precio * 0.7, 6),
            'es_servicio' => false,
            'activo' => true,
        ];
    }

    public function servicio(): static
    {
        return $this->state(['es_servicio' => true]);
    }
}
