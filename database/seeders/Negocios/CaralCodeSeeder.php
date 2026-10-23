<?php

namespace Database\Seeders\Negocios;

use App\Modulos\Suscripciones\Models\Plan;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Catalogo\Models\TipoUnidad;
use Illuminate\Database\Seeder;

/**
 * Caral Code: consultora de software, dueña del ERP RicoPet. Vende sus servicios (desarrollo, soporte,
 * capacitación) y la suscripción al ERP, que aquí es un producto más (un servicio por plan y periodicidad).
 * Es la empresa que emite la factura de cada pago de suscripción (SUSCRIPCIONES_EMISOR_RUC).
 */
class CaralCodeSeeder extends Seeder
{
    use SiembraNegocio;

    public const RUC = '20000000003';

    public function run(): void
    {
        $this->empresa([
            'ruc' => self::RUC, 'razon_social' => 'Caral Code SAC', 'nombre_comercial' => 'Caral Code',
            'direccion' => 'Av. Grau 450, oficina 302, Barranca - Lima', 'departamento' => 'Lima', 'provincia' => 'Barranca', 'distrito' => 'Barranca',
            'ubigeo' => '150201', 'telefono' => '(+51) 987-654-321', 'email' => 'contacto@caralcode.pe',
        ]);

        $this->usuario('jhonnatan@ricopet.com', 'Jhonnatan Yontonel', '47803338', 'Administrador');
        $this->usuario('ventas@caralcode.pe', 'Andrea', $this->claveDemo(), 'Vendedor', 'Quispe', '987111222');
        $this->usuario('contabilidad@caralcode.pe', 'Miguel', $this->claveDemo(), 'Contador', 'Huamán', '987333444');

        $this->categorias([
            'Suscripciones ERP'     => 'Planes del ERP RicoPet: mensual, trimestral y anual.',
            'Suscripciones web'     => 'Planes de la página web RicoPet: Catálogo y Tienda.',
            'Implementación'        => 'Puesta en marcha del ERP: configuración, migración de datos y facturación electrónica.',
            'Desarrollo de software' => 'Sistemas y módulos a medida, páginas web e integraciones.',
            'Soporte técnico'       => 'Soporte remoto y presencial, mantenimiento y bolsas de horas.',
            'Capacitación'          => 'Cursos para usuarios del ERP y talleres para equipos.',
            'Hosting y dominios'    => 'Alojamiento, dominios, correos corporativos y certificados.',
            'Equipos punto de venta' => 'Impresoras térmicas, lectoras, gavetas y equipos para caja.',
        ]);

        $this->suscripcionesComoServicios();

        $this->productos([
            ['categoria' => 'Implementación', 'servicio' => true, 'codigo' => 'IMP-BASICA', 'nombre' => 'Implementación ERP — un local', 'precio' => 350.00, 'descripcion' => 'Configuración de empresa, series, usuarios, carga de productos (hasta 300) y capacitación inicial.'],
            ['categoria' => 'Implementación', 'servicio' => true, 'codigo' => 'IMP-MULTI', 'nombre' => 'Implementación ERP — varios locales', 'precio' => 890.00],
            ['categoria' => 'Implementación', 'servicio' => true, 'codigo' => 'IMP-SUNAT', 'nombre' => 'Alta en facturación electrónica SUNAT', 'precio' => 180.00, 'descripcion' => 'Usuario SOL secundario, certificado digital y paso a producción.'],
            ['categoria' => 'Implementación', 'servicio' => true, 'codigo' => 'IMP-MIGRA', 'nombre' => 'Migración de datos desde otro sistema', 'precio' => 250.00],
            ['categoria' => 'Desarrollo de software', 'servicio' => true, 'codigo' => 'DEV-HORA', 'nombre' => 'Hora de desarrollo a medida', 'precio' => 75.00],
            ['categoria' => 'Desarrollo de software', 'servicio' => true, 'codigo' => 'DEV-WEB', 'nombre' => 'Página web informativa', 'precio' => 1200.00],
            ['categoria' => 'Desarrollo de software', 'servicio' => true, 'codigo' => 'DEV-TIENDA', 'nombre' => 'Tienda en línea integrada al ERP', 'precio' => 2800.00],
            ['categoria' => 'Desarrollo de software', 'servicio' => true, 'codigo' => 'DEV-API', 'nombre' => 'Integración por API con otro sistema', 'precio' => 950.00],
            ['categoria' => 'Soporte técnico', 'servicio' => true, 'codigo' => 'SOP-REMOTO', 'nombre' => 'Soporte remoto (hora)', 'precio' => 40.00],
            ['categoria' => 'Soporte técnico', 'servicio' => true, 'codigo' => 'SOP-VISITA', 'nombre' => 'Visita técnica presencial', 'precio' => 90.00],
            ['categoria' => 'Soporte técnico', 'servicio' => true, 'codigo' => 'SOP-BOLSA10', 'nombre' => 'Bolsa de 10 horas de soporte', 'precio' => 350.00],
            ['categoria' => 'Capacitación', 'servicio' => true, 'codigo' => 'CAP-USUARIO', 'nombre' => 'Capacitación de usuarios del ERP (2 horas)', 'precio' => 120.00],
            ['categoria' => 'Capacitación', 'servicio' => true, 'codigo' => 'CAP-TALLER', 'nombre' => 'Taller de facturación electrónica', 'precio' => 250.00],
            ['categoria' => 'Hosting y dominios', 'servicio' => true, 'codigo' => 'HOST-ANUAL', 'nombre' => 'Hosting anual', 'precio' => 240.00],
            ['categoria' => 'Hosting y dominios', 'servicio' => true, 'codigo' => 'DOM-PE', 'nombre' => 'Dominio .pe (1 año)', 'precio' => 120.00],
            ['categoria' => 'Hosting y dominios', 'servicio' => true, 'codigo' => 'CORREO-5', 'nombre' => 'Correo corporativo, 5 cuentas (1 año)', 'precio' => 180.00],
            ['categoria' => 'Equipos punto de venta', 'codigo' => 'EQ-IMP80', 'nombre' => 'Impresora térmica 80 mm USB/LAN', 'precio' => 389.00, 'costo' => 245.00, 'stock' => 6, 'minimo' => 2],
            ['categoria' => 'Equipos punto de venta', 'codigo' => 'EQ-IMP58', 'nombre' => 'Impresora térmica portátil 58 mm Bluetooth', 'precio' => 219.00, 'costo' => 135.00, 'stock' => 4, 'minimo' => 2],
            ['categoria' => 'Equipos punto de venta', 'codigo' => 'EQ-LECTORA', 'nombre' => 'Lectora de código de barras inalámbrica', 'precio' => 149.00, 'costo' => 82.00, 'stock' => 8, 'minimo' => 3],
            ['categoria' => 'Equipos punto de venta', 'codigo' => 'EQ-GAVETA', 'nombre' => 'Gaveta de dinero metálica', 'precio' => 199.00, 'costo' => 118.00, 'stock' => 5, 'minimo' => 2],
            ['categoria' => 'Equipos punto de venta', 'codigo' => 'EQ-ROLLO80', 'nombre' => 'Rollo de papel térmico 80 mm', 'unidad' => 'RLL', 'precio' => 4.50, 'costo' => 2.20, 'stock' => 100, 'minimo' => 30],
            ['categoria' => 'Equipos punto de venta', 'codigo' => 'EQ-BALANZA', 'nombre' => 'Balanza electrónica 30 kg con conexión a PC', 'precio' => 459.00, 'costo' => 300.00, 'stock' => 3, 'minimo' => 1],
        ]);
        $this->presentaciones([['Rollo de papel térmico 80 mm', 'CJ', 50, 195.00]]);

        // Sus clientes: los negocios que usan el ERP y otros prospectos
        $this->clientes([
            ['6', RicoPetSeeder::RUC, 'Rico Pet SAC', null, 'Calle Ferrocarril 235, Barranca', 'gerencia@ricopet.com', '987000111'],
            ['6', BoticaSeeder::RUC, 'Botica AFB EIRL', null, 'Jr. Zavala 512, Barranca', 'botica@gmail.com', '987000222'],
            ['6', TiendaVariedadesSeeder::RUC, 'Variedades La Esquina EIRL', null, 'Av. Lima 880, Supe Pueblo', 'laesquina@gmail.com', '987000333'],
            ['6', '20611223344', 'Ferretería El Constructor SAC', null, 'Av. Gálvez 120, Barranca', 'compras@elconstructor.pe', '987000444'],
            ['6', '10456789012', 'Lucía', 'Ramírez Torres', 'Jr. Castilla 340, Paramonga', 'lucia.ramirez@gmail.com', '987000555'],
            ['1', '45678912', 'Carlos', 'Mendoza Ríos', 'Urb. Los Jardines Mz B Lt 4, Huacho', 'cmendoza@hotmail.com', '987000666'],
        ]);

        $this->proveedores([
            ['20555123401', 'Tecnología Comercial del Norte SAC', 'TecNorte', 'Rubén Salas', '956111222', 'ventas@tecnorte.pe', 'Av. Wilson 1120, Lima', 'Cercado de Lima'],
            ['20555123402', 'Servicios Cloud Andinos SAC', 'CloudAndino', 'Paola Ríos', '956333444', 'soporte@cloudandino.pe', 'Av. Javier Prado 2450, San Borja', 'San Borja'],
            ['20555123403', 'Papelera Térmica Perú SAC', 'PapeTerm', 'José Ancajima', '956555666', 'pedidos@papeterm.pe', 'Jr. Paruro 820, Lima', 'Cercado de Lima'],
        ]);
        // Caral Code es la dueña del ERP: no lleva suscripción (sin plan = sin límites)
    }

    /**
     * La suscripción al ERP como servicios del catálogo: uno por plan activo y periodicidad, con el precio
     * vigente del plan. Se actualizan nombre y precio; los de planes o precios que ya no existen se desactivan.
     */
    private function suscripcionesComoServicios(): void
    {
        $vigentes = [];
        foreach (Plan::with('precios', 'producto')->where('activo', true)->orderBy('orden')->get() as $plan) {
            foreach ($plan->precios->where('activo', true)->where('precio', '>', 0) as $precio) {
                $codigo = self::codigoSuscripcion($plan->codigo, $precio->periodicidad);
                $periodo = config('suscripciones.periodicidades.' . $precio->periodicidad . '.0');
                Producto::updateOrCreate(['empresa_id' => $this->empresa->id, 'codigo' => $codigo], [
                    'categoria_id' => $this->categorias[$plan->producto->codigo === 'web' ? 'Suscripciones web' : 'Suscripciones ERP'], 'tipo_unidad_id' => TipoUnidad::where('codigo', 'SRV')->value('id'),
                    'nombre' => "Suscripción RicoPet {$plan->producto->nombre} — Plan {$plan->nombre} ({$periodo})", 'descripcion' => $plan->descripcion,
                    'precio_unitario' => (float) $precio->precio, 'costo_unitario' => 0, 'es_servicio' => true, 'maneja_lotes' => false, 'activo' => true,
                ]);
                $vigentes[] = $codigo;
            }
        }

        Producto::where('empresa_id', $this->empresa->id)->whereLike('codigo', 'SUSC-%')->whereNotIn('codigo', $vigentes)->update(['activo' => false]);
    }

    /** Código del servicio de suscripción (lo usa también el comprobante de cada pago) */
    public static function codigoSuscripcion(string $plan, string $periodicidad): string
    {
        return strtoupper("SUSC-{$plan}-{$periodicidad}");
    }
}
