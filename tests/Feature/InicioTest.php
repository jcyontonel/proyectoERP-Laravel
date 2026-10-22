<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

class InicioTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->empresa = $this->crearEmpresa();
    }

    public function test_el_administrador_ve_todos_los_bloques(): void
    {
        $this->actingAs($this->usuarioCon('Administrador', $this->empresa))
            ->get(route('inicio'))
            ->assertOk()
            ->assertSee('Ventas del día')
            ->assertSee('Rentabilidad del mes')
            ->assertSee('Cuentas pendientes')
            ->assertSee('Mi turno de caja')
            ->assertSee('Lotes por vencer')
            ->assertSee('Últimas compras')
            ->assertSee('Facturación electrónica');
    }

    public function test_el_vendedor_ve_solo_sus_ventas_y_no_las_finanzas(): void
    {
        $vendedor = $this->usuarioCon('Vendedor', $this->empresa);
        $otro     = $this->usuarioCon('Vendedor', $this->empresa);

        $this->venta($vendedor, 100);
        $this->venta($otro, 900);

        $this->actingAs($vendedor)
            ->get(route('inicio'))
            ->assertOk()
            ->assertSee('Mis ventas del día')
            ->assertSee('S/ 100.00')
            ->assertDontSee('S/ 900.00')
            ->assertDontSee('Rentabilidad del mes')
            ->assertDontSee('Cuentas pendientes');
    }

    public function test_el_contador_ve_las_ventas_de_toda_la_empresa(): void
    {
        $this->venta($this->usuarioCon('Vendedor', $this->empresa), 250);

        $this->actingAs($this->usuarioCon('Contador', $this->empresa))
            ->get(route('inicio'))
            ->assertOk()
            ->assertSee('Ventas del día')
            ->assertSee('S/ 250.00')
            ->assertSee('Rentabilidad del mes');
    }

    public function test_el_almacenero_ve_inventario_y_no_ventas(): void
    {
        $this->actingAs($this->usuarioCon('Almacenero', $this->empresa))
            ->get(route('inicio'))
            ->assertOk()
            ->assertSee('Inventario valorizado')
            ->assertSee('Stock crítico')
            ->assertDontSee('Ventas de la semana');
    }

    public function test_sin_empresa_asignada_no_hay_acceso(): void
    {
        $this->actingAs(User::factory()->create())->get(route('inicio'))->assertForbidden();
    }

    public function test_el_menu_muestra_modulos_futuros_y_su_pagina_proximamente(): void
    {
        $admin = $this->usuarioCon('Administrador', $this->empresa);

        $this->actingAs($admin)->get(route('inicio'))
            ->assertSee('Notas de crédito / débito')
            ->assertSee(route('pronto', 'notas'));

        $this->actingAs($admin)->get(route('pronto', 'notas'))->assertOk()->assertSee('Próximamente');
        $this->actingAs($admin)->get(route('pronto', 'no-existe'))->assertNotFound();
    }

    public function test_un_modulo_futuro_respeta_el_permiso(): void
    {
        $this->actingAs($this->usuarioCon('Vendedor', $this->empresa))
            ->get(route('pronto', 'usuarios'))
            ->assertForbidden();
    }

    public function test_cambiar_de_empresa_solo_a_una_asignada(): void
    {
        $otra  = $this->crearEmpresa(['nombre_comercial' => 'Sucursal Norte']);
        $ajena = $this->crearEmpresa();
        $user  = $this->usuarioCon('Administrador', $this->empresa);
        $user->empresas()->attach($otra->id);

        $this->actingAs($user)->post(route('empresa.cambiar'), ['empresa_id' => $otra->id])
            ->assertRedirect(route('inicio'))
            ->assertSessionHas('empresa_id', $otra->id);

        $this->actingAs($user)->post(route('empresa.cambiar'), ['empresa_id' => $ajena->id])
            ->assertSessionHas('error');
    }

    private function venta(User $user, float $total): Venta
    {
        return Venta::create([
            'empresa_id'    => $this->empresa->id,
            'almacen_id'    => Almacen::where('empresa_id', $this->empresa->id)->value('id'),
            'user_id'       => $user->id,
            'tipo'          => '03',
            'serie'         => 'B001',
            'numero'        => Venta::max('numero') + 1,
            'fecha_emision' => now(),
            'metodo_pago'   => 'efectivo',
            'estado'        => Venta::COMPLETADA,
            'subtotal'      => round($total / 1.18, 2),
            'total_impuestos' => round($total - $total / 1.18, 2),
            'total'         => $total,
        ]);
    }
}
