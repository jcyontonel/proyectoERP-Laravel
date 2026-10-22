<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class RicoPetSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Empresa principal
        $empresa = Empresa::firstOrCreate(
            ['ruc' => '20000000001'],
            [
                'razon_social' => 'Rico Pet SAC',
                'nombre_comercial' => 'RicoPet',
                'direccion' => 'Calle Ferrocarril 235, Barranca - Lima',
                'departamento' => 'Lima',
                'provincia' => 'Barranca',
                'distrito' => 'Barranca',
                'ubigeo' => '150201',
                'telefono' => '(+51) 123-4567',
                'email' => null,
                'moneda' => 'PEN',
                'activo' => true,
            ]
        );

        $usuarios = [
            ['name' => 'Jhonnatan Yontonel', 'email' => 'jhonnatan@ricopet.com', 'password' => '47803338', 'rol' => 'Administrador'],
            ['name' => 'Sofia Yontonel',     'email' => 'sofia@ricopet.com',     'password' => '47803338', 'rol' => 'Administrador'],
            ['name' => 'Carlos Yontonel',    'email' => 'carlos@ricopet.com',    'password' => '15850759', 'rol' => 'Administrador'],
            ['name' => 'Rosa Sotelo',        'email' => 'rosa@ricopet.com',      'password' => '15861646', 'rol' => 'Administrador'],
        ];

        // 2. Los roles se asignan DENTRO de la empresa (Spatie teams)
        app(PermissionRegistrar::class)->setPermissionsTeamId($empresa->id);

        foreach ($usuarios as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => Hash::make($data['password'])]
            );

            $user->empresas()->syncWithoutDetaching([$empresa->id]);

            if (! $user->hasRole($data['rol'])) {
                $user->assignRole($data['rol']);
            }
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }
}
