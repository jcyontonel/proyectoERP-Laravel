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
    private const UNIDADES = [1 => 'UND', 2 => 'CJ', 3 => 'PQT', 7 => 'KG', 8 => 'LT', 11 => 'BLS', 12 => 'SCO', 17 => 'FRS', 18 => 'BLT', 19 => 'SRV', 20 => 'PIP', 21 => 'PAS'];

    private const FRACCIONAMIENTOS = [
         ['Ricocan Adulto 15kg (Saco)', 'Ricocan Adulto 1 Kilo', 15],
        ['Ricocan Cachorro 15kg (Saco)', 'Ricocan Cachorro 1 Kilo', 15],
        ['Thor Adulto 20kg (Saco)', 'Thor Adulto 1/2 Kilo', 40], // 20kg = 40 medios kilos
        ['Thor Cachorro 20kg (Saco)', 'Thor Cachorro 1/2 Kilo', 40],
        ['Miraclot Adulto 20kg (Saco)', 'Miraclot Adulto 1/2 Kilo', 40],
        ['Miraclot Cachorro 20kg (Saco)', 'Miraclot Cachorro 1/2 Kilo', 40],
    ];

    /** [producto, unidad de la presentación, unidades base, precio] */
    private const PRESENTACIONES = [
        ['Ricotrack Adulto Hultsab (Caja)', 'CJ', 10, 80.00],
        ['Ricotrack Adulto Cordero (Caja)', 'CJ', 10, 80.00],
        ['Kissto Atún y Pollo (Paquete 4 unid.)', 'PQT', 6, 60.00],
        ['Kissto Atún y Salmón (Paquete 4 unid.)', 'PQT', 6, 60.00],
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

        $this->clientes([]);

        $this->proveedores([]);

        $this->suscripcion('total', 'anual', pagado: true);

        // Página web con el plan Tienda (en prueba): carrito, pedidos, envíos y pago con tarjeta en modo prueba
        $this->paginaWeb('tienda', [
            'slug' => 'ricopet', 'titulo' => 'RicoPet', 'lema' => 'Todo para tu mascota, con envío a domicilio',
            'descripcion' => "Alimentos, snacks y juguetes para perros y gatos.\nPide en línea y te lo llevamos el mismo día en Barranca.",
            'whatsapp' => '51987654321', 'color' => '#0f766e', 'recojo_en_tienda' => true, 'pedido_minimo' => 20,
            'instrucciones_pago' => "Yape o Plin al 987 654 321 (RicoPet SAC).\nTransferencia BCP 191-0000000-0-00.",
        ], [
            'Alimento seco Ricocan Adulto Pollo 15kg', 'Alimento seco Pedigree Adulto Carne 3kg', 'Juguete pelota Kong Classic M', 'Snack Pedigree Dentastix Medianos 7 unidades',
            'Alimento seco Ricocan Adulto Pollo 1kg', 'Alimento seco Dog Chow Adulto Medianos 1.5kg', 'Snack Dog Chow Huesitos 60g', 'Snack Ricocan Tiras de Carne 70g',
            'Snack Nutrican Soft Bites 50g', 'Snack Kongo Huesos de Cuero 2 unidades', 'Juguete soga tecida 30cm', 'Juguete pelota con sonido 10cm', 'Juguete frisbee de goma 20cm',
        ], zonas: [['Barranca centro', 5, 'El mismo día'], ['Paramonga y Supe', 10, '24 horas'], ['Huacho', 15, '24 a 48 horas']]);
    }
}
