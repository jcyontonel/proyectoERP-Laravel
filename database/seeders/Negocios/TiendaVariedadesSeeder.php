<?php

namespace Database\Seeders\Negocios;

use Illuminate\Database\Seeder;

/**
 * Variedades La Esquina: minimarket de barrio con productos variados (abarrotes, bebidas, limpieza,
 * útiles, bazar). Venta por kilo partiendo sacos y presentaciones por caja o pack.
 */
class TiendaVariedadesSeeder extends Seeder
{
    use SiembraNegocio;

    public const RUC = '20000000004';

    public function run(): void
    {
        $this->empresa([
            'ruc' => self::RUC, 'razon_social' => 'Variedades La Esquina EIRL', 'nombre_comercial' => 'La Esquina',
            'direccion' => 'Av. Lima 880, Supe Pueblo - Barranca', 'departamento' => 'Lima', 'provincia' => 'Barranca', 'distrito' => 'Supe',
            'ubigeo' => '150204', 'telefono' => '(+51) 987-000-333', 'email' => 'laesquina@gmail.com',
        ]);

        $this->usuario('jhonnatan@ricopet.com', 'Jhonnatan Yontonel', '47803338', 'Administrador');
        $this->usuario('pepe@laesquina.pe', 'José', $this->claveDemo(), 'Administrador', 'Cabrera', '966300100');
        $this->usuario('caja@laesquina.pe', 'Milagros', $this->claveDemo(), 'Vendedor', 'Rojas', '966300101');
        $this->usuario('almacen@laesquina.pe', 'Luis', $this->claveDemo(), 'Almacenero', 'Paredes', '966300102');

        $this->categorias([
            'Abarrotes'               => 'Arroz, azúcar, aceite, fideos, menestras y conservas.',
            'Bebidas'                 => 'Gaseosas, agua, jugos y rehidratantes.',
            'Lácteos y huevos'        => 'Leche, yogurt, queso, mantequilla y huevos.',
            'Frutas y verduras'       => 'Productos frescos por kilo.',
            'Panadería'               => 'Pan del día y queques.',
            'Snacks y golosinas'      => 'Galletas, chocolates, caramelos y piqueos.',
            'Limpieza del hogar'      => 'Detergentes, lejía, lavavajillas y desinfectantes.',
            'Cuidado personal'        => 'Jabón, shampoo, papel higiénico y desodorantes.',
            'Licores'                 => 'Cervezas, vinos y destilados (solo mayores de edad).',
            'Útiles escolares y oficina' => 'Cuadernos, lapiceros, papel y útiles.',
            'Bazar y plásticos'       => 'Táperes, baldes, vasos y artículos para el hogar.',
            'Recargas y servicios'    => 'Recargas y pagos de servicios.',
        ]);

        $this->productos([
            // Abarrotes (por saco y por kilo)
            ['categoria' => 'Abarrotes', 'unidad' => 'SCO', 'codigo' => '7750000000011', 'nombre' => 'Arroz superior saco 50 kg', 'precio' => 195.00, 'costo' => 150.00, 'stock' => 4, 'minimo' => 1],
            ['categoria' => 'Abarrotes', 'unidad' => 'KG', 'codigo' => '7750000000012', 'nombre' => 'Arroz superior por kilo', 'precio' => 4.20, 'stock' => 0],
            ['categoria' => 'Abarrotes', 'unidad' => 'SCO', 'codigo' => '7750000000013', 'nombre' => 'Azúcar rubia saco 50 kg', 'precio' => 180.00, 'costo' => 140.00, 'stock' => 3, 'minimo' => 1],
            ['categoria' => 'Abarrotes', 'unidad' => 'KG', 'codigo' => '7750000000014', 'nombre' => 'Azúcar rubia por kilo', 'precio' => 3.90, 'stock' => 0],
            ['categoria' => 'Abarrotes', 'unidad' => 'UND', 'codigo' => '7750000000015', 'nombre' => 'Aceite vegetal botella 1 L', 'precio' => 9.80, 'stock' => 36, 'minimo' => 12],
            ['categoria' => 'Abarrotes', 'unidad' => 'BLS', 'codigo' => '7750000000016', 'nombre' => 'Fideos spaghetti bolsa 500 g', 'precio' => 3.20, 'stock' => 40, 'minimo' => 12],
            ['categoria' => 'Abarrotes', 'unidad' => 'BLS', 'codigo' => '7750000000017', 'nombre' => 'Lentejas bolsa 500 g', 'precio' => 4.50, 'stock' => 20],
            ['categoria' => 'Abarrotes', 'unidad' => 'LTA', 'codigo' => '7750000000018', 'nombre' => 'Atún en trozos lata 170 g', 'precio' => 5.90, 'stock' => 30],
            ['categoria' => 'Abarrotes', 'unidad' => 'BLS', 'codigo' => '7750000000019', 'nombre' => 'Sal de mesa yodada bolsa 1 kg', 'precio' => 1.50, 'stock' => 25],
            ['categoria' => 'Abarrotes', 'unidad' => 'BLS', 'codigo' => '7750000000020', 'nombre' => 'Avena en hojuelas bolsa 900 g', 'precio' => 7.50, 'stock' => 15],
            // Bebidas
            ['categoria' => 'Bebidas', 'unidad' => 'UND', 'codigo' => '7750000000101', 'nombre' => 'Gaseosa sabor cola botella 500 ml', 'precio' => 2.50, 'stock' => 48, 'minimo' => 24],
            ['categoria' => 'Bebidas', 'unidad' => 'UND', 'codigo' => '7750000000102', 'nombre' => 'Gaseosa sabor cola botella 3 L', 'precio' => 11.50, 'stock' => 12],
            ['categoria' => 'Bebidas', 'unidad' => 'UND', 'codigo' => '7750000000103', 'nombre' => 'Agua mineral sin gas 625 ml', 'precio' => 1.50, 'stock' => 60, 'minimo' => 24],
            ['categoria' => 'Bebidas', 'unidad' => 'UND', 'codigo' => '7750000000104', 'nombre' => 'Bebida rehidratante 500 ml', 'precio' => 3.00, 'stock' => 24],
            ['categoria' => 'Bebidas', 'unidad' => 'UND', 'codigo' => '7750000000105', 'nombre' => 'Néctar de durazno 1 L', 'precio' => 4.80, 'stock' => 18],
            // Lácteos y huevos
            ['categoria' => 'Lácteos y huevos', 'unidad' => 'LTA', 'codigo' => '7750000000201', 'nombre' => 'Leche evaporada entera lata 400 g', 'precio' => 4.20, 'stock' => 96, 'minimo' => 48],
            ['categoria' => 'Lácteos y huevos', 'unidad' => 'UND', 'codigo' => '7750000000202', 'nombre' => 'Yogurt bebible fresa 1 L', 'precio' => 6.90, 'stock' => 15],
            ['categoria' => 'Lácteos y huevos', 'unidad' => 'KG', 'codigo' => '7750000000203', 'nombre' => 'Queso fresco por kilo', 'precio' => 22.00, 'stock' => 5, 'minimo' => 2],
            ['categoria' => 'Lácteos y huevos', 'unidad' => 'KG', 'codigo' => '7750000000204', 'nombre' => 'Huevos rosados por kilo', 'precio' => 8.50, 'stock' => 20, 'minimo' => 5],
            ['categoria' => 'Lácteos y huevos', 'unidad' => 'UND', 'codigo' => '7750000000205', 'nombre' => 'Mantequilla con sal 200 g', 'precio' => 8.90, 'stock' => 10],
            // Frutas y verduras
            ['categoria' => 'Frutas y verduras', 'unidad' => 'KG', 'codigo' => 'FV-001', 'nombre' => 'Papa amarilla por kilo', 'precio' => 3.50, 'stock' => 40, 'minimo' => 10],
            ['categoria' => 'Frutas y verduras', 'unidad' => 'KG', 'codigo' => 'FV-002', 'nombre' => 'Cebolla roja por kilo', 'precio' => 2.80, 'stock' => 30, 'minimo' => 10],
            ['categoria' => 'Frutas y verduras', 'unidad' => 'KG', 'codigo' => 'FV-003', 'nombre' => 'Tomate por kilo', 'precio' => 3.20, 'stock' => 20, 'minimo' => 5],
            ['categoria' => 'Frutas y verduras', 'unidad' => 'KG', 'codigo' => 'FV-004', 'nombre' => 'Plátano de seda por kilo', 'precio' => 3.00, 'stock' => 25, 'minimo' => 5],
            ['categoria' => 'Frutas y verduras', 'unidad' => 'KG', 'codigo' => 'FV-005', 'nombre' => 'Limón por kilo', 'precio' => 4.50, 'stock' => 15, 'minimo' => 5],
            // Panadería
            ['categoria' => 'Panadería', 'unidad' => 'UND', 'codigo' => 'PAN-001', 'nombre' => 'Pan francés', 'precio' => 0.25, 'stock' => 200, 'minimo' => 50],
            ['categoria' => 'Panadería', 'unidad' => 'UND', 'codigo' => 'PAN-002', 'nombre' => 'Queque de vainilla', 'precio' => 12.00, 'stock' => 6],
            // Snacks y golosinas
            ['categoria' => 'Snacks y golosinas', 'unidad' => 'PQT', 'codigo' => '7750000000301', 'nombre' => 'Galletas de soda paquete x 6', 'precio' => 2.50, 'stock' => 40],
            ['categoria' => 'Snacks y golosinas', 'unidad' => 'UND', 'codigo' => '7750000000302', 'nombre' => 'Chocolate con leche barra 30 g', 'precio' => 1.50, 'stock' => 50],
            ['categoria' => 'Snacks y golosinas', 'unidad' => 'BLS', 'codigo' => '7750000000303', 'nombre' => 'Papas fritas clásicas bolsa 45 g', 'precio' => 2.00, 'stock' => 40],
            ['categoria' => 'Snacks y golosinas', 'unidad' => 'BLS', 'codigo' => '7750000000304', 'nombre' => 'Caramelos de menta bolsa x 100', 'precio' => 6.50, 'stock' => 10],
            // Limpieza del hogar
            ['categoria' => 'Limpieza del hogar', 'unidad' => 'BLS', 'codigo' => '7750000000401', 'nombre' => 'Detergente en polvo bolsa 800 g', 'precio' => 9.50, 'stock' => 20],
            ['categoria' => 'Limpieza del hogar', 'unidad' => 'UND', 'codigo' => '7750000000402', 'nombre' => 'Lejía tradicional botella 1 L', 'precio' => 3.80, 'stock' => 24],
            ['categoria' => 'Limpieza del hogar', 'unidad' => 'UND', 'codigo' => '7750000000403', 'nombre' => 'Lavavajillas en crema 360 g', 'precio' => 4.50, 'stock' => 20],
            ['categoria' => 'Limpieza del hogar', 'unidad' => 'UND', 'codigo' => '7750000000404', 'nombre' => 'Desinfectante aroma lavanda 900 ml', 'precio' => 6.90, 'stock' => 15],
            ['categoria' => 'Limpieza del hogar', 'unidad' => 'PQT', 'codigo' => '7750000000405', 'nombre' => 'Esponja doble uso paquete x 3', 'precio' => 3.50, 'stock' => 20],
            // Cuidado personal
            ['categoria' => 'Cuidado personal', 'unidad' => 'PQT', 'codigo' => '7750000000501', 'nombre' => 'Papel higiénico doble hoja paquete x 4', 'precio' => 5.90, 'stock' => 30],
            ['categoria' => 'Cuidado personal', 'unidad' => 'UND', 'codigo' => '7750000000502', 'nombre' => 'Jabón de tocador 125 g', 'precio' => 2.80, 'stock' => 40],
            ['categoria' => 'Cuidado personal', 'unidad' => 'SBR', 'codigo' => '7750000000503', 'nombre' => 'Shampoo sachet 10 ml', 'precio' => 0.80, 'stock' => 100],
            ['categoria' => 'Cuidado personal', 'unidad' => 'UND', 'codigo' => '7750000000504', 'nombre' => 'Desodorante en barra 50 g', 'precio' => 11.90, 'stock' => 10],
            // Licores
            ['categoria' => 'Licores', 'unidad' => 'LTA', 'codigo' => '7750000000601', 'nombre' => 'Cerveza rubia lata 355 ml', 'precio' => 4.50, 'stock' => 72, 'minimo' => 24],
            ['categoria' => 'Licores', 'unidad' => 'UND', 'codigo' => '7750000000602', 'nombre' => 'Cerveza rubia botella 620 ml', 'precio' => 7.00, 'stock' => 36],
            ['categoria' => 'Licores', 'unidad' => 'UND', 'codigo' => '7750000000603', 'nombre' => 'Vino tinto semiseco 750 ml', 'precio' => 22.00, 'stock' => 8],
            ['categoria' => 'Licores', 'unidad' => 'UND', 'codigo' => '7750000000604', 'nombre' => 'Ron añejo 750 ml', 'precio' => 39.90, 'stock' => 6],
            // Útiles
            ['categoria' => 'Útiles escolares y oficina', 'unidad' => 'UND', 'codigo' => '7750000000701', 'nombre' => 'Cuaderno A4 cuadriculado 100 hojas', 'precio' => 6.50, 'stock' => 30],
            ['categoria' => 'Útiles escolares y oficina', 'unidad' => 'UND', 'codigo' => '7750000000702', 'nombre' => 'Lapicero azul punta fina', 'precio' => 1.00, 'stock' => 100, 'minimo' => 20],
            ['categoria' => 'Útiles escolares y oficina', 'unidad' => 'PQT', 'codigo' => '7750000000703', 'nombre' => 'Papel bond A4 75 g paquete x 500', 'precio' => 18.50, 'stock' => 10],
            ['categoria' => 'Útiles escolares y oficina', 'unidad' => 'UND', 'codigo' => '7750000000704', 'nombre' => 'Pegamento en barra 21 g', 'precio' => 3.50, 'stock' => 20],
            // Bazar
            ['categoria' => 'Bazar y plásticos', 'unidad' => 'UND', 'codigo' => 'BAZ-001', 'nombre' => 'Táper hermético 1 L', 'precio' => 7.50, 'stock' => 12],
            ['categoria' => 'Bazar y plásticos', 'unidad' => 'UND', 'codigo' => 'BAZ-002', 'nombre' => 'Balde plástico 12 L', 'precio' => 9.90, 'stock' => 8],
            ['categoria' => 'Bazar y plásticos', 'unidad' => 'PQT', 'codigo' => 'BAZ-003', 'nombre' => 'Vasos descartables 7 oz paquete x 50', 'precio' => 4.50, 'stock' => 20],
            ['categoria' => 'Bazar y plásticos', 'unidad' => 'PQT', 'codigo' => 'BAZ-004', 'nombre' => 'Bolsas para basura 30 L paquete x 10', 'precio' => 3.90, 'stock' => 20],
            // Servicios
            ['categoria' => 'Recargas y servicios', 'servicio' => true, 'codigo' => 'SRV-REC', 'nombre' => 'Recarga de celular', 'precio' => 10.00],
            ['categoria' => 'Recargas y servicios', 'servicio' => true, 'codigo' => 'SRV-FOTO', 'nombre' => 'Fotocopia o impresión (página)', 'precio' => 0.20],
        ]);

        $this->fraccionamientos([
            ['Arroz superior saco 50 kg', 'Arroz superior por kilo', 50],
            ['Azúcar rubia saco 50 kg', 'Azúcar rubia por kilo', 50],
        ]);
        $this->presentaciones([
            ['Aceite vegetal botella 1 L', 'CJ', 12, 112.00],
            ['Gaseosa sabor cola botella 500 ml', 'PQT', 12, 27.00],
            ['Leche evaporada entera lata 400 g', 'CJ', 48, 192.00],
            ['Cerveza rubia lata 355 ml', 'CJ', 24, 99.00],
            ['Lapicero azul punta fina', 'CJ', 50, 40.00],
        ]);

        $this->clientes([
            ['1', '47112233', 'Yolanda', 'Huamán Sánchez', 'Calle Bolívar 120, Supe', null, '959300100'],
            ['1', '47223344', 'Félix', 'Ramos Ochoa', 'Av. Lima 702, Supe', null, '959300101'],
            ['1', '47334455', 'Elena', 'Castro Vidal', 'Jr. Grau 45, Supe Puerto', 'elena.castro@gmail.com', '959300102'],
            ['1', '47445566', 'Marco', 'Inga Tello', 'Urb. Las Flores Mz D Lt 3, Supe', null, '959300103'],
            ['6', '20609988771', 'Restaurante El Buen Sabor EIRL', null, 'Av. Lima 950, Supe', 'elbuensabor@gmail.com', '959300104'],
        ]);

        $this->proveedores([
            ['20555000301', 'Distribuidora Mayorista Norte Chico SAC', 'Mayorista Norte Chico', 'Alberto Vega', '964300100', 'pedidos@mayoristanc.pe', 'Av. Grau 1500, Huacho', 'Huacho'],
            ['20555000302', 'Bebidas y Refrescos del Norte SAC', 'BebiNorte', 'Sandra Luján', '964300101', 'ventas@bebinorte.pe', 'Panamericana Norte km 185, Barranca', 'Barranca'],
            ['20555000303', 'Lácteos del Valle SAC', 'Lácteos del Valle', 'Hernán Díaz', '964300102', 'distribucion@lacteosvalle.pe', 'Av. Industrial 88, Huaral', 'Huaral'],
            ['20555000304', 'Comercial Limpieza Total EIRL', 'Limpieza Total', 'Rocío Alva', '964300103', 'ventas@limpiezatotal.pe', 'Jr. Ica 450, Lima', 'Cercado de Lima'],
        ]);

        $this->suscripcion('basico', 'mensual', pagado: false);   // en periodo de prueba
    }
}
