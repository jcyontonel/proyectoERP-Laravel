<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\TipoDocumento;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClienteSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $empresa = Empresa::where('ruc', '20000000001')->firstOrFail();

            // Catálogo SUNAT 06: 1 = DNI, 4 = CE
            $dni = TipoDocumento::where('codigo', '1')->firstOrFail();
            $ce  = TipoDocumento::where('codigo', '4')->firstOrFail();

            $clientes = Cliente::factory()
                ->count(10)
                ->sequence(fn ($s) => ['tipo_documento_id' => $s->index === 7 ? $ce->id : $dni->id])
                ->create();

            // Un cliente CE con documento alfanumérico, como lo valida ClienteRequest
            $clientes[7]->update(['numero_documento' => strtoupper(fake()->bothify('??######'))]);

            $empresa->clientes()->syncWithoutDetaching($clientes->pluck('id'));
        });
    }
}
