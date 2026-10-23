<?php

namespace Tests\Feature;

use App\Modulos\Erp\Models\Almacen;
use App\Modulos\Erp\Models\Cliente;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Nucleo\Models\TipoDocumento;
use App\Modulos\Erp\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

class ClienteTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->empresa = $this->crearEmpresa();
    }

    public function test_registrar_cliente_lo_vincula_a_la_empresa(): void
    {
        $this->actingAs($this->usuarioCon('Vendedor', $this->empresa))
            ->post(route('clientes.store'), $this->datos(['numero_documento' => '74821563']))
            ->assertRedirect()
            ->assertSessionHas('status');

        $c = Cliente::where('numero_documento', '74821563')->firstOrFail();
        $this->assertTrue($c->empresas()->whereKey($this->empresa->id)->exists());
    }

    public function test_el_mismo_documento_en_otra_empresa_solo_se_vincula(): void
    {
        $otra = $this->crearEmpresa();
        $this->actingAs($this->usuarioCon('Vendedor', $otra))->post(route('clientes.store'), $this->datos(['numero_documento' => '42678910']));

        $this->actingAs($this->usuarioCon('Vendedor', $this->empresa))
            ->post(route('clientes.store'), $this->datos(['numero_documento' => '42678910', 'nombre' => 'Otro nombre']))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'vinculado'));

        $this->assertSame(1, Cliente::where('numero_documento', '42678910')->count());
        $this->assertSame(2, Cliente::where('numero_documento', '42678910')->first()->empresas()->count());
    }

    public function test_valida_el_dni_de_8_digitos(): void
    {
        $this->actingAs($this->usuarioCon('Vendedor', $this->empresa))
            ->post(route('clientes.store'), $this->datos(['numero_documento' => '123']))
            ->assertSessionHasErrors('numero_documento');
    }

    public function test_listado_y_ficha_solo_de_clientes_vinculados(): void
    {
        $propio = $this->cliente($this->empresa, 'Valeria Mendoza');
        $ajeno  = $this->cliente($this->crearEmpresa(), 'Cliente Ajeno');
        $user   = $this->usuarioCon('Administrador', $this->empresa);

        $this->actingAs($user)->get(route('clientes.index'))->assertOk()->assertSee('Valeria Mendoza')->assertDontSee('Cliente Ajeno');
        $this->actingAs($user)->get(route('clientes.show', $propio))->assertOk()->assertSee('Productos favoritos');
        $this->actingAs($user)->get(route('clientes.show', $ajeno))->assertForbidden();
        $this->actingAs($user)->get(route('clientes.edit', $propio))->assertOk();
    }

    public function test_el_almacenero_no_puede_registrar_clientes(): void
    {
        $this->actingAs($this->usuarioCon('Almacenero', $this->empresa))
            ->post(route('clientes.store'), $this->datos(['numero_documento' => '70345621']))
            ->assertForbidden();
    }

    public function test_no_se_quita_un_cliente_con_ventas(): void
    {
        $c = $this->cliente($this->empresa, 'Andrea Torres');
        $admin = $this->usuarioCon('Administrador', $this->empresa);
        Venta::create([
            'empresa_id' => $this->empresa->id, 'almacen_id' => Almacen::where('empresa_id', $this->empresa->id)->value('id'),
            'cliente_id' => $c->id, 'user_id' => $admin->id, 'tipo' => '03', 'serie' => 'B001', 'numero' => 1,
            'fecha_emision' => now(), 'estado' => Venta::COMPLETADA, 'total' => 50,
        ]);

        $this->actingAs($admin)->delete(route('clientes.destroy', $c))->assertSessionHas('error');
        $this->assertTrue($c->empresas()->whereKey($this->empresa->id)->exists());
    }

    private function datos(array $extra = []): array
    {
        return $extra + [
            'tipo_documento_id' => TipoDocumento::where('codigo', '1')->value('id'),
            'nombre'            => 'Carlos',
            'apellido'          => 'Ramírez',
            'telefono'          => '987654321',
            'activo'            => 1,
        ];
    }

    private function cliente(Empresa $empresa, string $nombre): Cliente
    {
        $c = Cliente::factory()->create(['nombre' => $nombre, 'apellido' => null]);
        $c->empresas()->attach($empresa->id);

        return $c;
    }
}
