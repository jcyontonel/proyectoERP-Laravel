<?php

namespace Database\Seeders\Negocios;

use Illuminate\Database\Seeder;

/**
 * Botica AFB: farmacia. Medicamentos con lote y vencimiento, venta por tableta/cápsula partiendo la caja,
 * y dos roles propios del negocio: Químico farmacéutico (responsable) y Técnico de farmacia (atención).
 */
class BoticaSeeder extends Seeder
{
    use SiembraNegocio;

    public const RUC = '20000000002';

    public function run(): void
    {
        $this->empresa([
            'ruc' => self::RUC, 'razon_social' => 'Botica AFB EIRL', 'nombre_comercial' => 'Botica AFB',
            'direccion' => 'Jr. Zavala 512, Barranca - Lima', 'departamento' => 'Lima', 'provincia' => 'Barranca', 'distrito' => 'Barranca',
            'ubigeo' => '150201', 'telefono' => '(+51) 987-000-222', 'email' => 'botica@gmail.com',
        ]);

        // Tipos de usuario de la botica (roles propios de la empresa)
        $this->rolPropio('Químico farmacéutico', [
            'ver_ventas', 'emitir-ventas', 'editar_ventas', 'emitir-notas', 'gestionar-clientes', 'gestionar-caja', 'registrar-pagos',
            'gestionar-productos', 'gestionar-inventario', 'aprobar-ajustes', 'gestionar-compras', 'gestionar-proveedores', 'ver-reportes',
        ]);
        $this->rolPropio('Técnico de farmacia', ['ver_ventas', 'emitir-ventas', 'gestionar-clientes', 'gestionar-caja', 'registrar-pagos', 'gestionar-inventario']);

        $this->usuario('jhonnatan@ricopet.com', 'Jhonnatan Yontonel', '47803338', 'Administrador');
        $this->usuario('botica@gmail.com', 'Jhonnatan', $this->claveDemo(), 'Administrador', 'Sotelo');
        $this->usuario('qf@boticaafb.pe', 'Patricia', $this->claveDemo(), 'Químico farmacéutico', 'Valverde', '955100100');
        $this->usuario('tecnico@boticaafb.pe', 'Brenda', $this->claveDemo(), 'Técnico de farmacia', 'Solís', '955100101');

        $this->categorias([
            'Analgésicos y antiinflamatorios' => 'Alivio del dolor, la fiebre y la inflamación.',
            'Antigripales y respiratorio'     => 'Resfríos, tos, congestión y afecciones respiratorias.',
            'Antialérgicos'                   => 'Antihistamínicos para rinitis, urticaria y alergias.',
            'Antibióticos'                    => 'Venta solo con receta médica.',
            'Gastrointestinal'                => 'Acidez, diarrea, rehidratación y malestar estomacal.',
            'Vitaminas y suplementos'         => 'Multivitamínicos, vitamina C, calcio y suplementos.',
            'Dermatología'                    => 'Cremas, antimicóticos, protectores solares e hidratantes.',
            'Primeros auxilios'               => 'Gasas, vendas, antisépticos y curaciones.',
            'Bebés y maternidad'              => 'Pañales, fórmulas, toallitas y cuidado del bebé.',
            'Cuidado personal'                => 'Higiene diaria: jabones, shampoo, cuidado bucal.',
            'Dispositivos médicos'            => 'Termómetros, tensiómetros, glucómetros y mascarillas.',
            'Servicios'                       => 'Servicios de la botica.',
        ]);

        $lote = fn (string $codigo, int $meses, int $cantidad) => [$codigo, now()->addMonths($meses)->toDateString(), $cantidad];

        $this->productos([
            // Se venden por caja o por tableta/cápsula (la caja se parte al vender)
            ['categoria' => 'Analgésicos y antiinflamatorios', 'unidad' => 'CJ', 'codigo' => 'MED-0001', 'nombre' => 'Paracetamol 500 mg caja x 100 tabletas', 'precio' => 18.00, 'costo' => 9.50, 'stock' => 12],
            ['categoria' => 'Analgésicos y antiinflamatorios', 'unidad' => 'UND', 'codigo' => 'MED-0002', 'nombre' => 'Paracetamol 500 mg tableta', 'precio' => 0.30, 'stock' => 0],
            ['categoria' => 'Analgésicos y antiinflamatorios', 'unidad' => 'CJ', 'codigo' => 'MED-0003', 'nombre' => 'Ibuprofeno 400 mg caja x 100 tabletas', 'precio' => 25.00, 'costo' => 13.00, 'stock' => 8],
            ['categoria' => 'Analgésicos y antiinflamatorios', 'unidad' => 'UND', 'codigo' => 'MED-0004', 'nombre' => 'Ibuprofeno 400 mg tableta', 'precio' => 0.40, 'stock' => 0],
            ['categoria' => 'Analgésicos y antiinflamatorios', 'unidad' => 'BLT', 'codigo' => 'MED-0005', 'nombre' => 'Naproxeno 550 mg blíster x 10 tabletas', 'precio' => 6.50, 'lotes' => [$lote('NAP2401', 18, 25), $lote('NAP2310', 2, 6)]],
            ['categoria' => 'Analgésicos y antiinflamatorios', 'unidad' => 'UND', 'codigo' => 'MED-0006', 'nombre' => 'Diclofenaco gel 1 % tubo 50 g', 'precio' => 12.90, 'lotes' => [$lote('DIC2402', 20, 15)]],
            ['categoria' => 'Antigripales y respiratorio', 'unidad' => 'CJ', 'codigo' => 'MED-0007', 'nombre' => 'Antigripal paracetamol + fenilefrina caja x 12 tabletas', 'precio' => 14.50, 'lotes' => [$lote('ANT2403', 14, 20)]],
            ['categoria' => 'Antigripales y respiratorio', 'unidad' => 'FRS', 'codigo' => 'MED-0008', 'nombre' => 'Jarabe para la tos dextrometorfano 120 ml', 'precio' => 16.90, 'lotes' => [$lote('JAR2311', 3, 8), $lote('JAR2405', 22, 12)]],
            ['categoria' => 'Antigripales y respiratorio', 'unidad' => 'FRS', 'codigo' => 'MED-0009', 'nombre' => 'Ambroxol jarabe 30 mg/5 ml 120 ml', 'precio' => 11.50, 'lotes' => [$lote('AMB2404', 16, 10)]],
            ['categoria' => 'Antialérgicos', 'unidad' => 'BLT', 'codigo' => 'MED-0010', 'nombre' => 'Loratadina 10 mg blíster x 10 tabletas', 'precio' => 3.50, 'lotes' => [$lote('LOR2402', 24, 30)]],
            ['categoria' => 'Antialérgicos', 'unidad' => 'BLT', 'codigo' => 'MED-0011', 'nombre' => 'Cetirizina 10 mg blíster x 10 tabletas', 'precio' => 4.00, 'lotes' => [$lote('CET2401', 20, 20)]],
            ['categoria' => 'Antialérgicos', 'unidad' => 'BLT', 'codigo' => 'MED-0012', 'nombre' => 'Clorfenamina 4 mg blíster x 10 tabletas', 'precio' => 2.00, 'stock' => 40],
            ['categoria' => 'Antibióticos', 'unidad' => 'CJ', 'codigo' => 'MED-0013', 'nombre' => 'Amoxicilina 500 mg caja x 100 cápsulas', 'precio' => 45.00, 'costo' => 24.00, 'stock' => 6],
            ['categoria' => 'Antibióticos', 'unidad' => 'UND', 'codigo' => 'MED-0014', 'nombre' => 'Amoxicilina 500 mg cápsula', 'precio' => 0.60, 'stock' => 0],
            ['categoria' => 'Antibióticos', 'unidad' => 'CJ', 'codigo' => 'MED-0015', 'nombre' => 'Azitromicina 500 mg caja x 3 tabletas', 'precio' => 12.50, 'lotes' => [$lote('AZI2403', 18, 15)]],
            ['categoria' => 'Antibióticos', 'unidad' => 'FRS', 'codigo' => 'MED-0016', 'nombre' => 'Cefalexina 250 mg/5 ml suspensión 60 ml', 'precio' => 14.00, 'lotes' => [$lote('CEF2402', 12, 10)]],
            ['categoria' => 'Gastrointestinal', 'unidad' => 'CJ', 'codigo' => 'MED-0017', 'nombre' => 'Omeprazol 20 mg caja x 100 cápsulas', 'precio' => 30.00, 'costo' => 15.00, 'stock' => 8],
            ['categoria' => 'Gastrointestinal', 'unidad' => 'UND', 'codigo' => 'MED-0018', 'nombre' => 'Omeprazol 20 mg cápsula', 'precio' => 0.40, 'stock' => 0],
            ['categoria' => 'Gastrointestinal', 'unidad' => 'SBR', 'codigo' => 'MED-0019', 'nombre' => 'Sales de rehidratación oral sobre 27.9 g', 'precio' => 1.80, 'lotes' => [$lote('SRO2404', 30, 60)]],
            ['categoria' => 'Gastrointestinal', 'unidad' => 'FRS', 'codigo' => 'MED-0020', 'nombre' => 'Subsalicilato de bismuto suspensión 150 ml', 'precio' => 19.90, 'lotes' => [$lote('BIS2402', 15, 10)]],
            ['categoria' => 'Gastrointestinal', 'unidad' => 'BLT', 'codigo' => 'MED-0021', 'nombre' => 'Hioscina butilbromuro 10 mg blíster x 10', 'precio' => 5.50, 'lotes' => [$lote('HIO2401', 10, 20)]],
            ['categoria' => 'Vitaminas y suplementos', 'unidad' => 'UND', 'codigo' => 'VIT-0001', 'nombre' => 'Vitamina C 1 g efervescente tubo x 10', 'precio' => 9.90, 'lotes' => [$lote('VTC2405', 24, 25)]],
            ['categoria' => 'Vitaminas y suplementos', 'unidad' => 'FRS', 'codigo' => 'VIT-0002', 'nombre' => 'Multivitamínico adulto frasco x 30 tabletas', 'precio' => 39.90, 'lotes' => [$lote('MUL2403', 18, 10)]],
            ['categoria' => 'Vitaminas y suplementos', 'unidad' => 'FRS', 'codigo' => 'VIT-0003', 'nombre' => 'Calcio + vitamina D3 frasco x 60 tabletas', 'precio' => 34.90, 'lotes' => [$lote('CAL2404', 20, 8)]],
            ['categoria' => 'Vitaminas y suplementos', 'unidad' => 'FRS', 'codigo' => 'VIT-0004', 'nombre' => 'Sulfato ferroso jarabe 180 ml', 'precio' => 15.50, 'lotes' => [$lote('FER2402', 14, 10)]],
            ['categoria' => 'Dermatología', 'unidad' => 'UND', 'codigo' => 'DER-0001', 'nombre' => 'Clotrimazol crema 1 % tubo 20 g', 'precio' => 7.50, 'lotes' => [$lote('CLO2403', 22, 15)]],
            ['categoria' => 'Dermatología', 'unidad' => 'UND', 'codigo' => 'DER-0002', 'nombre' => 'Bloqueador solar FPS 50 frasco 120 ml', 'precio' => 49.90, 'lotes' => [$lote('BLO2405', 26, 10)]],
            ['categoria' => 'Dermatología', 'unidad' => 'UND', 'codigo' => 'DER-0003', 'nombre' => 'Crema hidratante corporal 200 ml', 'precio' => 22.50, 'stock' => 12],
            ['categoria' => 'Dermatología', 'unidad' => 'UND', 'codigo' => 'DER-0004', 'nombre' => 'Hidrocortisona crema 1 % tubo 15 g', 'precio' => 8.90, 'lotes' => [$lote('HID2402', 18, 10)]],
            ['categoria' => 'Primeros auxilios', 'unidad' => 'UND', 'codigo' => 'PAU-0001', 'nombre' => 'Alcohol medicinal 70° frasco 1 L', 'precio' => 9.50, 'stock' => 20],
            ['categoria' => 'Primeros auxilios', 'unidad' => 'FRS', 'codigo' => 'PAU-0002', 'nombre' => 'Agua oxigenada 10 vol. frasco 120 ml', 'precio' => 3.50, 'stock' => 25],
            ['categoria' => 'Primeros auxilios', 'unidad' => 'PQT', 'codigo' => 'PAU-0003', 'nombre' => 'Gasa estéril 10 x 10 cm paquete x 10', 'precio' => 4.50, 'stock' => 30],
            ['categoria' => 'Primeros auxilios', 'unidad' => 'RLL', 'codigo' => 'PAU-0004', 'nombre' => 'Esparadrapo 5 cm x 4.5 m', 'precio' => 6.00, 'stock' => 15],
            ['categoria' => 'Primeros auxilios', 'unidad' => 'CJ', 'codigo' => 'PAU-0005', 'nombre' => 'Curitas caja x 100 unidades', 'precio' => 12.00, 'stock' => 10],
            ['categoria' => 'Primeros auxilios', 'unidad' => 'RLL', 'codigo' => 'PAU-0006', 'nombre' => 'Venda elástica 4 pulgadas', 'precio' => 5.50, 'stock' => 15],
            ['categoria' => 'Bebés y maternidad', 'unidad' => 'PQT', 'codigo' => 'BEB-0001', 'nombre' => 'Pañales para bebé talla M paquete x 30', 'precio' => 42.90, 'stock' => 12],
            ['categoria' => 'Bebés y maternidad', 'unidad' => 'PQT', 'codigo' => 'BEB-0002', 'nombre' => 'Toallitas húmedas para bebé paquete x 80', 'precio' => 8.90, 'stock' => 20],
            ['categoria' => 'Bebés y maternidad', 'unidad' => 'LTA', 'codigo' => 'BEB-0003', 'nombre' => 'Fórmula infantil etapa 1 lata 400 g', 'precio' => 69.90, 'lotes' => [$lote('FOR2403', 12, 8)]],
            ['categoria' => 'Bebés y maternidad', 'unidad' => 'UND', 'codigo' => 'BEB-0004', 'nombre' => 'Crema antipañalitis tubo 60 g', 'precio' => 18.50, 'lotes' => [$lote('APN2402', 20, 10)]],
            ['categoria' => 'Cuidado personal', 'unidad' => 'UND', 'codigo' => 'CPE-0001', 'nombre' => 'Jabón antibacterial 90 g', 'precio' => 3.20, 'stock' => 40],
            ['categoria' => 'Cuidado personal', 'unidad' => 'FRS', 'codigo' => 'CPE-0002', 'nombre' => 'Shampoo anticaspa 400 ml', 'precio' => 21.90, 'stock' => 12],
            ['categoria' => 'Cuidado personal', 'unidad' => 'UND', 'codigo' => 'CPE-0003', 'nombre' => 'Pasta dental con flúor 90 g', 'precio' => 5.50, 'stock' => 30],
            ['categoria' => 'Cuidado personal', 'unidad' => 'UND', 'codigo' => 'CPE-0004', 'nombre' => 'Cepillo dental cerdas suaves', 'precio' => 4.50, 'stock' => 30],
            ['categoria' => 'Cuidado personal', 'unidad' => 'FRS', 'codigo' => 'CPE-0005', 'nombre' => 'Alcohol en gel 250 ml', 'precio' => 7.90, 'stock' => 25],
            ['categoria' => 'Dispositivos médicos', 'unidad' => 'UND', 'codigo' => 'DIS-0001', 'nombre' => 'Termómetro digital', 'precio' => 15.00, 'stock' => 10],
            ['categoria' => 'Dispositivos médicos', 'unidad' => 'UND', 'codigo' => 'DIS-0002', 'nombre' => 'Tensiómetro digital de brazo', 'precio' => 129.00, 'costo' => 82.00, 'stock' => 4, 'minimo' => 1],
            ['categoria' => 'Dispositivos médicos', 'unidad' => 'KIT', 'codigo' => 'DIS-0003', 'nombre' => 'Glucómetro con 10 tiras reactivas', 'precio' => 89.00, 'costo' => 55.00, 'stock' => 5, 'minimo' => 1],
            ['categoria' => 'Dispositivos médicos', 'unidad' => 'CJ', 'codigo' => 'DIS-0004', 'nombre' => 'Mascarillas KN95 caja x 10', 'precio' => 15.00, 'stock' => 20],
            ['categoria' => 'Servicios', 'servicio' => true, 'codigo' => 'SRV-0001', 'nombre' => 'Aplicación de inyectable', 'precio' => 5.00],
            ['categoria' => 'Servicios', 'servicio' => true, 'codigo' => 'SRV-0002', 'nombre' => 'Toma de presión arterial', 'precio' => 2.00],
            ['categoria' => 'Servicios', 'servicio' => true, 'codigo' => 'SRV-0003', 'nombre' => 'Prueba de glucosa', 'precio' => 8.00],
        ]);

        $this->fraccionamientos([
            ['Paracetamol 500 mg caja x 100 tabletas', 'Paracetamol 500 mg tableta', 100],
            ['Ibuprofeno 400 mg caja x 100 tabletas', 'Ibuprofeno 400 mg tableta', 100],
            ['Amoxicilina 500 mg caja x 100 cápsulas', 'Amoxicilina 500 mg cápsula', 100],
            ['Omeprazol 20 mg caja x 100 cápsulas', 'Omeprazol 20 mg cápsula', 100],
        ]);
        $this->presentaciones([
            ['Clorfenamina 4 mg blíster x 10 tabletas', 'CJ', 10, 18.00],
            ['Gasa estéril 10 x 10 cm paquete x 10', 'CJ', 10, 40.00],
            ['Sales de rehidratación oral sobre 27.9 g', 'CJ', 20, 32.00],
        ]);

        $this->clientes([
            ['1', '40112233', 'Carmen', 'Villanueva Ríos', 'Jr. Zavala 300, Barranca', null, '958200100'],
            ['1', '40223344', 'Julio', 'Espinoza Lara', 'Av. Gálvez 410, Barranca', 'julio.espinoza@gmail.com', '958200101'],
            ['1', '40334455', 'Teresa', 'Morales Cruz', 'Calle Arica 77, Barranca', null, '958200102'],
            ['1', '40445566', 'Ricardo', 'Flores Ugarte', 'Urb. Santa Rosa Mz A Lt 8, Supe', 'rflores@hotmail.com', '958200103'],
            ['1', '40556677', 'Gloria', 'Cárdenas Paz', 'Jr. Lima 610, Pativilca', null, '958200104'],
            ['6', '20608877661', 'Clínica San Pedro de Barranca SAC', null, 'Av. Grau 1020, Barranca', 'logistica@clinicasanpedro.pe', '958200105'],
        ]);

        $this->proveedores([
            ['20555000201', 'Droguería Salud Total SAC', 'Salud Total', 'Mónica Aguirre', '963200100', 'pedidos@saludtotal.pe', 'Av. Venezuela 1450, Lima', 'Breña'],
            ['20555000202', 'Distribuidora Farmacéutica del Norte SAC', 'FarmaNorte', 'César Linares', '963200101', 'ventas@farmanorte.pe', 'Av. Industrial 210, Huacho', 'Huacho'],
            ['20555000203', 'Insumos Médicos Lima EIRL', 'InsuMed', 'Gabriela Soto', '963200102', 'contacto@insumed.pe', 'Jr. Cangallo 330, Lima', 'Cercado de Lima'],
        ]);

        $this->suscripcion('basico', 'mensual', pagado: true);
    }
}
