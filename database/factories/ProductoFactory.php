<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Empresa;
use App\Models\Categoria;
use App\Models\TipoUnidad;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Producto>
 */
class ProductoFactory extends Factory
{
    public function definition(): array
    {
        // Elegir una empresa, categoría y tipo de unidad existentes
        $empresa = Empresa::inRandomOrder()->first();
        $categoria = $empresa 
            ? Categoria::where('empresa_id', $empresa->id)->inRandomOrder()->first()
            : Categoria::inRandomOrder()->first();

        $tipoUnidad = TipoUnidad::inRandomOrder()->first();

        // Generar un nombre de producto único por empresa
        $nombre = ucfirst($this->faker->unique()->sentence(4));

        return [
            'empresa_id' => $empresa ? $empresa->id : Empresa::factory(),
            'categoria_id' => $categoria ? $categoria->id : Categoria::factory(),
            'tipo_unidad_id' => $tipoUnidad ? $tipoUnidad->id : TipoUnidad::factory(),
            'codigo' => $this->faker->ean13(),
            'nombre' => $nombre,
            'descripcion' => $this->faker->sentence(10), 
            'precio_unitario' => $this->faker->randomFloat(2, 3, 100),
            'stock' => $this->faker->numberBetween(5, 100),
            'es_servicio' => false,
            'activo' => true,
        ];
    }
}
