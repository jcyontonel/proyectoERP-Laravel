<?php

namespace Database\Seeders\Negocios;

use Illuminate\Database\Seeder;

/**
 * RicoPet: tienda de mascotas (alimentos, accesorios, higiene y salud para perros, gatos, roedores, aves y peces).
 * Catálogo en data/ricopet_categorias.php y data/ricopet_productos.php; venta por kilo partiendo sacos.
 */
class RicoPetSeeder extends Seeder
{
    use SiembraNegocio;

    public const RUC = '20000000001';

    /** tipo_unidad_id de ricopet_productos.php → código de unidad (orden de TipoUnidadSeeder) */
    private const UNIDADES = [1 => 'UND', 2 => 'CJ', 3 => 'PQT', 7 => 'KG', 8 => 'LT', 11 => 'BLS', 12 => 'SCO', 17 => 'FRS', 18 => 'BLT', 19 => 'SRV'];

    private const FRACCIONAMIENTOS = [
        ['Alimento seco Ricocan Adulto Pollo 15kg', 'Alimento seco Ricocan Adulto Pollo por kilo', 15],
        ['Alimento seco Whiskas Gatito Pollo 10kg', 'Alimento seco Whiskas Gatito Pollo por kilo', 10],
        ['Arena sanitaria aglomerante 10kg', 'Arena sanitaria aglomerante por kilo', 10],
        ['Snack Pedigree Dentastix Medianos 7 unidades', 'Snack Pedigree Dentastix Medianos (unidad)', 7],
    ];

    /** [producto, unidad de la presentación, unidades base, precio] */
    private const PRESENTACIONES = [
        ['Alimento seco Ricocan Adulto Pollo 1kg', 'CJ', 10, 118.00],
        ['Snack Felix Gelatinas 4 unidades', 'CJ', 12, 90.00],
        ['Toallitas húmedas felinas 50 unidades', 'PQT', 3, 22.00],
    ];

    public function run(): void
    {
        $this->empresa([
            'ruc' => self::RUC, 'razon_social' => 'Rico Pet SAC', 'nombre_comercial' => 'RicoPet',
            'direccion' => 'Calle Ferrocarril 235, Barranca - Lima', 'departamento' => 'Lima', 'provincia' => 'Barranca', 'distrito' => 'Barranca',
            'ubigeo' => '150201', 'telefono' => '(+51) 123-4567', 'email' => 'ventas@ricopet.com',
        ]);

        $this->usuario('jhonnatan@ricopet.com', 'Jhonnatan Yontonel', '47803338', 'Administrador');
        $this->usuario('sofia@ricopet.com', 'Sofia Yontonel', '47803338', 'Administrador');
        $this->usuario('carlos@ricopet.com', 'Carlos Yontonel', '15850759', 'Administrador');
        $this->usuario('rosa@ricopet.com', 'Rosa Sotelo', '15861646', 'Administrador');
        $this->usuario('caja@ricopet.com', 'Diana', $this->claveDemo(), 'Vendedor', 'Flores', '944111222');
        $this->usuario('almacen@ricopet.com', 'Kevin', $this->claveDemo(), 'Almacenero', 'Ramos', '944333444');

        $categorias = require database_path('seeders/data/ricopet_categorias.php');
        $this->categorias($categorias);
        $nombres = array_keys($categorias);

        $datos = require database_path('seeders/data/ricopet_productos.php');
        $this->productos(array_map(fn (array $p, int $i) => [
            'categoria' => $nombres[$p['categoria_id'] - 1],
            'unidad'    => self::UNIDADES[$p['tipo_unidad_id']] ?? 'UND',
            'codigo'    => filled($p['codigo'] ?? null) ? $p['codigo'] : sprintf('RP%04d', $i + 1),
            'nombre'    => $p['nombre'],
            'descripcion' => $p['descripcion'] ?? null,
            'precio'    => $p['precio_unitario'],
            'costo'     => $p['costo_unitario'] ?? null,
            'servicio'  => $p['es_servicio'] ?? false,
            'stock'     => 5,
            'minimo'    => 5,
        ], $datos, array_keys($datos)));

        $this->fraccionamientos(self::FRACCIONAMIENTOS);
        $this->presentaciones(self::PRESENTACIONES);

        $this->clientes([
            ['1', '41234567', 'María', 'Gonzales Paredes', 'Jr. Castilla 210, Barranca', 'maria.gonzales@gmail.com', '951100200'],
            ['1', '42345678', 'José', 'Huertas Villanueva', 'Av. Lima 455, Barranca', 'jose.huertas@hotmail.com', '951100201'],
            ['1', '43456789', 'Ana', 'Castillo Romero', 'Calle Gálvez 98, Barranca', null, '951100202'],
            ['1', '44567890', 'Luis', 'Pérez Chávez', 'Urb. San Luis Mz C Lt 12, Barranca', 'lperez@gmail.com', '951100203'],
            ['1', '45678901', 'Rosa', 'Quispe Mamani', 'Jr. Bolognesi 330, Supe', null, '951100204'],
            ['1', '46789012', 'Jorge', 'Salazar Medina', 'Av. Miraflores 170, Paramonga', 'jsalazar@gmail.com', '951100205'],
            ['4', 'CE0012345', 'Daniela', 'Rojas Pérez', 'Calle Zavala 45, Barranca', 'daniela.rojas@gmail.com', '951100206'],
            ['6', '20604455661', 'Veterinaria Huellitas SAC', null, 'Av. Grau 780, Barranca', 'compras@huellitas.pe', '951100207'],
            ['6', '10412345671', 'Pedro', 'Lozano Vega', 'Jr. Arequipa 120, Pativilca', null, '951100208'],
        ]);

        $this->proveedores([
            ['20555000101', 'Distribuidora de Alimentos para Mascotas del Norte SAC', 'DistriMascotas', 'Hugo Paredes', '962100100', 'pedidos@distrimascotas.pe', 'Av. Industrial 450, Huacho', 'Huacho'],
            ['20555000102', 'Comercial Animalia SAC', 'Animalia', 'Fiorella Campos', '962100101', 'ventas@animalia.pe', 'Av. Argentina 2100, Lima', 'Cercado de Lima'],
            ['20555000103', 'Importadora Pet Accesorios EIRL', 'PetAcces', 'Raúl Torres', '962100102', 'contacto@petacces.pe', 'Jr. Huallaga 650, Lima', 'Cercado de Lima'],
            ['20555000104', 'Laboratorios Veterinarios Andinos SAC', 'LabVet Andino', 'Carmen Ruiz', '962100103', 'pedidos@labvetandino.pe', 'Av. La Marina 1500, San Miguel', 'San Miguel'],
        ]);

        $this->suscripcion('total', 'anual', pagado: true);
    }
}
