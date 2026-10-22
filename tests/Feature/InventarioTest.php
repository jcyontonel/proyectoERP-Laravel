<?php

namespace Tests\Feature;

use App\Models\AjusteInventario;
use App\Models\Almacen;
use App\Models\AlmacenProducto;
use App\Models\Empresa;
use App\Models\Kardex;
use App\Models\MotivoAjuste;
use App\Models\TransferenciaAlmacen;
use Database\Seeders\MotivoAjusteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

class InventarioTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->empresa = $this->crearEmpresa();
        $this->seed(MotivoAjusteSeeder::class);
    }

    public function test_ajuste_de_ingreso_confirmado_suma_stock_y_deja_kardex(): void
    {
        $p = $this->productoConStock($this->empresa, 5);

        $this->actingAs($this->usuarioCon('Almacenero', $this->empresa))
            ->post(route('ajustes.store'), $this->ajuste('ingreso', 'HALLAZGO', 'confirmar', [['id' => $p->id, 'cantidad' => 3]]))
            ->assertRedirect();

        // HALLAZGO requiere aprobación: el almacenero no puede aprobar → queda pendiente
        $ajuste = AjusteInventario::firstOrFail();
        $this->assertSame(AjusteInventario::PENDIENTE, $ajuste->estado);
        $this->assertEquals(5.0, $this->stock($p->id));

        $this->actingAs($this->usuarioCon('Administrador', $this->empresa))
            ->patch(route('ajustes.confirmar', $ajuste))->assertSessionHas('status');

        $this->assertSame(AjusteInventario::CONFIRMADO, $ajuste->refresh()->estado);
        $this->assertEquals(8.0, $this->stock($p->id));
        $this->assertSame(1, Kardex::where('referencia_tipo', 'ajuste')->where('referencia_id', $ajuste->id)->count());
    }

    public function test_conteo_fisico_ajusta_la_diferencia(): void
    {
        $p = $this->productoConStock($this->empresa, 10);
        $admin = $this->usuarioCon('Administrador', $this->empresa);

        $this->actingAs($admin)->post(route('ajustes.store'), $this->ajuste('conteo', 'CONTEO', 'confirmar', [['id' => $p->id, 'cantidad' => 7]]))
            ->assertRedirect();

        $this->assertEquals(7.0, $this->stock($p->id));
        $this->assertEquals(-3.0, AjusteInventario::firstOrFail()->detalles()->value('cantidad'));
        $this->actingAs($admin)->get(route('ajustes.show', AjusteInventario::first()))->assertOk()->assertSee('Conteo físico');
    }

    public function test_salida_mayor_al_stock_no_se_aplica(): void
    {
        $p = $this->productoConStock($this->empresa, 2);

        $this->actingAs($this->usuarioCon('Administrador', $this->empresa))
            ->post(route('ajustes.store'), $this->ajuste('egreso', 'CONSUMO', 'confirmar', [['id' => $p->id, 'cantidad' => 5]]))
            ->assertSessionHasErrors('productos');

        $this->assertEquals(2.0, $this->stock($p->id));
        $this->assertSame(0, AjusteInventario::count());   // la transacción se revirtió completa
    }

    public function test_motivo_incompatible_con_el_tipo(): void
    {
        $p = $this->productoConStock($this->empresa, 2);

        $this->actingAs($this->usuarioCon('Administrador', $this->empresa))
            ->post(route('ajustes.store'), $this->ajuste('ingreso', 'MERMA', 'borrador', [['id' => $p->id, 'cantidad' => 1]]))
            ->assertSessionHasErrors('motivo_ajuste_id');
    }

    public function test_transferencia_envio_recepcion_con_faltante(): void
    {
        $destino = Almacen::create(['empresa_id' => $this->empresa->id, 'codigo' => 'ALM-02', 'nombre' => 'Sede Norte']);
        $p = $this->productoConStock($this->empresa, 10);
        $user = $this->usuarioCon('Almacenero', $this->empresa);

        $this->actingAs($user)->post(route('transferencias.store'), [
            'accion' => 'enviar', 'almacen_origen_id' => $this->almacen($this->empresa)->id, 'almacen_destino_id' => $destino->id,
            'fecha_movimiento' => now()->toDateString(), 'productos' => [['id' => $p->id, 'cantidad' => 4]],
        ])->assertRedirect();

        $t = TransferenciaAlmacen::firstOrFail();
        $this->assertSame(TransferenciaAlmacen::EN_TRANSITO, $t->estado);
        $this->assertEquals(6.0, $this->stock($p->id));
        $this->assertEquals(0.0, $this->stock($p->id, $destino->id));

        $detalle = $t->detalles()->first();
        $this->actingAs($user)->patch(route('transferencias.recibir', $t), ['recibidas' => [$detalle->id => 3]])->assertSessionHas('status');

        $t->refresh();
        $this->assertSame(TransferenciaAlmacen::RECIBIDA, $t->estado);
        $this->assertEquals(3.0, $this->stock($p->id, $destino->id));
        $this->assertStringContainsString('faltaron 1', $t->observacion);
        $this->actingAs($user)->get(route('transferencias.show', $t))->assertOk();
    }

    public function test_anular_transferencia_en_transito_devuelve_al_origen(): void
    {
        $destino = Almacen::create(['empresa_id' => $this->empresa->id, 'codigo' => 'ALM-02', 'nombre' => 'Sede Norte']);
        $p = $this->productoConStock($this->empresa, 10);
        $user = $this->usuarioCon('Administrador', $this->empresa);

        $this->actingAs($user)->post(route('transferencias.store'), [
            'accion' => 'enviar', 'almacen_origen_id' => $this->almacen($this->empresa)->id, 'almacen_destino_id' => $destino->id,
            'fecha_movimiento' => now()->toDateString(), 'productos' => [['id' => $p->id, 'cantidad' => 4]],
        ]);
        $this->actingAs($user)->patch(route('transferencias.anular', TransferenciaAlmacen::first()))->assertSessionHas('status');

        $this->assertEquals(10.0, $this->stock($p->id));
    }

    public function test_pantallas_de_inventario_y_configuracion_cargan(): void
    {
        $admin = $this->usuarioCon('Administrador', $this->empresa);
        $this->productoConStock($this->empresa, 3);

        foreach (['kardex.index', 'kardex.export', 'ajustes.index', 'ajustes.create', 'transferencias.index', 'lotes.index',
                  'categorias.index', 'categorias.create', 'almacenes.index', 'almacenes.create', 'cajas.index', 'cajas.create',
                  'motivos.index', 'motivos.create', 'caja.turnos.index', 'caja.movimientos.index'] as $ruta) {
            $this->actingAs($admin)->get(route($ruta))->assertOk();
        }
    }

    public function test_el_vendedor_no_accede_a_inventario(): void
    {
        $this->actingAs($this->usuarioCon('Vendedor', $this->empresa))->get(route('ajustes.index'))->assertForbidden();
    }

    private function ajuste(string $tipo, string $motivo, string $accion, array $productos): array
    {
        return [
            'accion'           => $accion,
            'tipo'             => $tipo,
            'almacen_id'       => $this->almacen($this->empresa)->id,
            'motivo_ajuste_id' => MotivoAjuste::where('empresa_id', $this->empresa->id)->where('codigo', $motivo)->value('id'),
            'fecha_movimiento' => now()->toDateString(),
            'productos'        => $productos,
        ];
    }

    private function stock(int $productoId, ?int $almacenId = null): float
    {
        return (float) AlmacenProducto::where('producto_id', $productoId)
            ->where('almacen_id', $almacenId ?? $this->almacen($this->empresa)->id)->value('stock');
    }
}
