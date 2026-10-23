<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Página web de cada empresa (producto "web" de la plataforma):
 *  - tiendas: una por empresa; su dirección pública es /{slug} (en desarrollo /tienda/{slug}).
 *    Culqi va por tienda: el dinero de cada venta es de la empresa (llaves cifradas; modo prueba o producción).
 *  - productos.publicar_web: solo lo publicado explícitamente aparece en la web (nunca todo el catálogo).
 *  - zonas_envio, pedidos_web y su detalle (plan Tienda).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiendas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->unique()->constrained('empresas')->restrictOnDelete();
            $table->string('slug', 60)->unique();
            $table->boolean('publicada')->default(false);
            $table->string('titulo', 120)->nullable();            // nombre visible (por defecto el comercial)
            $table->string('lema', 160)->nullable();
            $table->text('descripcion')->nullable();
            $table->string('whatsapp', 20)->nullable();           // con código de país: 51999888777
            $table->string('correo')->nullable();
            $table->string('color', 7)->default('#1773ab');
            $table->boolean('mostrar_stock')->default(false);
            $table->boolean('recojo_en_tienda')->default(true);
            $table->string('direccion_recojo')->nullable();
            $table->decimal('pedido_minimo', 10, 2)->default(0);
            $table->json('metodos_pago')->nullable();              // contra_entrega, transferencia, yape, tarjeta
            $table->text('instrucciones_pago')->nullable();       // cuentas, número de Yape…
            $table->string('culqi_modo', 12)->default('prueba');  // prueba | produccion
            $table->string('culqi_llave_publica')->nullable();
            $table->text('culqi_llave_secreta')->nullable();      // cifrada
            $table->timestamps();
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('publicar_web')->default(false)->after('activo');
            $table->boolean('destacado_web')->default(false)->after('publicar_web');
            $table->string('imagen_path')->nullable()->after('destacado_web');
            $table->index(['empresa_id', 'publicar_web'], 'idx_productos_empresa_web');
        });

        Schema::create('zonas_envio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->string('nombre', 100);
            $table->decimal('costo', 10, 2)->default(0);
            $table->string('tiempo_entrega', 60)->nullable();      // "24 a 48 horas"
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['empresa_id', 'nombre']);
        });

        Schema::create('pedidos_web', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->unsignedInteger('numero');                     // correlativo por empresa: W-000001
            $table->string('estado', 15)->default('nuevo');        // nuevo, confirmado, enviado, entregado, cancelado
            $table->string('cliente_nombre');
            $table->string('cliente_tipo_doc', 1)->nullable();     // 1 DNI, 6 RUC, 4 CE
            $table->string('cliente_documento', 15)->nullable();
            $table->string('cliente_telefono', 20);
            $table->string('cliente_correo')->nullable();
            $table->string('entrega', 10);                         // envio | recojo
            $table->foreignId('zona_envio_id')->nullable()->constrained('zonas_envio')->nullOnDelete();
            $table->string('direccion')->nullable();
            $table->string('referencia')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('costo_envio', 10, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('metodo_pago', 20);
            $table->string('pago_estado', 12)->default('pendiente');   // pendiente | pagado
            $table->string('culqi_cargo_id', 60)->nullable();
            $table->text('notas')->nullable();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'estado']);
        });

        Schema::create('detalle_pedidos_web', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos_web')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad', 12, 3);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_pedidos_web');
        Schema::dropIfExists('pedidos_web');
        Schema::dropIfExists('zonas_envio');
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex('idx_productos_empresa_web');
            $table->dropColumn(['publicar_web', 'destacado_web', 'imagen_path']);
        });
        Schema::dropIfExists('tiendas');
    }
};
