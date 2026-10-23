<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Suscripciones de la plataforma:
 *  - productos_plataforma: lo que se vende (ERP, Página web). Cada plan es de un producto.
 *  - titulares: quien paga (RUC o DNI). Un titular puede pagar varias suscripciones de varias empresas;
 *    los comprobantes de los pagos salen a su nombre.
 *  - suscripcion_empresa: empresas que cubre una suscripción (hasta planes.max_empresas). Los demás límites
 *    (comprobantes, productos, usuarios, almacenes) son por empresa. suscripciones.empresa_id queda como
 *    la empresa principal (la que la contrató).
 * Datos: los planes existentes pasan al producto ERP; cada empresa con suscripciones recibe su titular (sus
 * mismos datos fiscales) y cada suscripción cubre a su empresa. Se crean los planes web Catálogo y Tienda.
 */
return new class extends Migration
{
    private const MESES = ['mensual' => 1, 'trimestral' => 3, 'anual' => 12];

    public function up(): void
    {
        Schema::create('productos_plataforma', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();      // erp, web (más adelante: restaurante, canchas…)
            $table->string('nombre', 60);
            $table->string('descripcion', 255)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('titulares', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_documento', 1);          // catálogo SUNAT 06: 6 RUC, 1 DNI, 4 CE
            $table->string('numero_documento', 15)->unique();
            $table->string('nombre');                     // razón social o nombre completo
            $table->string('correo')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('direccion')->nullable();
            $table->timestamps();
        });

        Schema::table('planes', function (Blueprint $table) {
            $table->foreignId('producto_id')->nullable()->after('id')->constrained('productos_plataforma')->restrictOnDelete();
            $table->unsignedInteger('max_empresas')->nullable()->default(1)->after('max_almacenes');   // por suscripción; null = ilimitado
        });

        Schema::table('suscripciones', function (Blueprint $table) {
            $table->foreignId('titular_id')->nullable()->after('empresa_id')->constrained('titulares')->restrictOnDelete();
        });

        Schema::create('suscripcion_empresa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suscripcion_id')->constrained('suscripciones')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['suscripcion_id', 'empresa_id']);
            $table->index('empresa_id');
        });

        DB::transaction(function () {
            $ahora = now();
            DB::table('productos_plataforma')->insert([
                ['codigo' => 'erp', 'nombre' => 'ERP', 'descripcion' => 'Ventas, caja, facturación electrónica, inventario, compras y finanzas.', 'orden' => 1, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['codigo' => 'web', 'nombre' => 'Página web', 'descripcion' => 'La página de la empresa con sus productos: catálogo o tienda con carrito, pedidos y envíos.', 'orden' => 2, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ]);
            $erp = DB::table('productos_plataforma')->where('codigo', 'erp')->value('id');
            $web = DB::table('productos_plataforma')->where('codigo', 'web')->value('id');

            DB::table('planes')->update(['producto_id' => $erp]);
            foreach (['pro' => 2, 'total' => 5] as $codigo => $max) {
                DB::table('planes')->where('codigo', $codigo)->update(['max_empresas' => $max]);
            }

            $orden = (int) DB::table('planes')->max('orden');
            foreach ([
                'catalogo' => ['Catálogo', 'Tu carta o muestrario en internet: productos, precios y contacto por WhatsApp.', ['web_catalogo'], ['mensual' => 29, 'trimestral' => 79, 'anual' => 290]],
                'tienda'   => ['Tienda', 'Tienda en línea: carrito, pedidos, pago con tarjeta y envíos por zona.', ['web_catalogo', 'web_tienda'], ['mensual' => 69, 'trimestral' => 189, 'anual' => 690]],
            ] as $codigo => [$nombre, $descripcion, $modulos, $precios]) {
                if (DB::table('planes')->where('codigo', $codigo)->exists()) {
                    continue;
                }
                $id = DB::table('planes')->insertGetId([
                    'producto_id' => $web, 'codigo' => $codigo, 'nombre' => $nombre, 'descripcion' => $descripcion, 'modulos' => json_encode($modulos),
                    'max_empresas' => 1, 'dias_prueba' => 15, 'activo' => true, 'orden' => ++$orden, 'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
                foreach ($precios as $periodicidad => $precio) {
                    DB::table('plan_precios')->insert(['plan_id' => $id, 'periodicidad' => $periodicidad, 'meses' => self::MESES[$periodicidad], 'precio' => $precio, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
                }
            }

            // Titular = la propia empresa (sus datos fiscales); la suscripción cubre a su empresa
            foreach (DB::table('suscripciones')->select('empresa_id')->distinct()->pluck('empresa_id') as $empresaId) {
                $e = DB::table('empresas')->find($empresaId);
                $doc = (string) $e->ruc;
                $titular = DB::table('titulares')->where('numero_documento', $doc)->value('id')
                    ?? DB::table('titulares')->insertGetId([
                        'tipo_documento' => strlen($doc) === 11 ? '6' : '1', 'numero_documento' => $doc, 'nombre' => $e->razon_social,
                        'correo' => $e->email, 'telefono' => $e->telefono, 'direccion' => $e->direccion, 'created_at' => $ahora, 'updated_at' => $ahora,
                    ]);
                DB::table('suscripciones')->where('empresa_id', $empresaId)->update(['titular_id' => $titular]);
            }
            DB::table('suscripciones')->orderBy('id')->get(['id', 'empresa_id', 'created_at'])->each(fn ($s) => DB::table('suscripcion_empresa')->insert([
                'suscripcion_id' => $s->id, 'empresa_id' => $s->empresa_id, 'created_at' => $s->created_at, 'updated_at' => $s->created_at,
            ]));
        });

        Schema::table('planes', function (Blueprint $table) {
            $table->unsignedBigInteger('producto_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        $web = DB::table('planes')->whereIn('codigo', ['catalogo', 'tienda'])->pluck('id');
        if (DB::table('suscripciones')->whereIn('plan_id', $web)->exists()) {
            throw new RuntimeException('Hay suscripciones a planes web: no se puede revertir sin perderlas.');
        }
        DB::table('plan_precios')->whereIn('plan_id', $web)->delete();
        DB::table('planes')->whereIn('id', $web)->delete();

        Schema::dropIfExists('suscripcion_empresa');
        Schema::table('suscripciones', fn (Blueprint $table) => $table->dropConstrainedForeignId('titular_id'));
        Schema::table('planes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producto_id');
            $table->dropColumn('max_empresas');
        });
        Schema::dropIfExists('titulares');
        Schema::dropIfExists('productos_plataforma');
    }
};
