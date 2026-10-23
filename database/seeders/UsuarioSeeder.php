<?php

namespace Database\Seeders;

use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Nucleo\Models\Role;
use App\Modulos\Nucleo\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/** Un usuario por rol en cada empresa (excepto RicoPet): vendedor3@erp.com / password */
class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $roles = Role::all();

        foreach (Empresa::where('ruc', '!=', '20000000001')->get() as $empresa) {
            $registrar->setPermissionsTeamId($empresa->id);

            foreach ($roles as $rol) {
                $user = User::firstOrCreate(
                    ['email' => Str::lower($rol->name) . $empresa->id . '@erp.com'],
                    ['name' => $rol->name . ' ' . fake()->firstName(), 'password' => Hash::make('password')]
                );

                $user->empresas()->syncWithoutDetaching([$empresa->id]);
                $user->assignRole($rol);
            }
        }

        $registrar->setPermissionsTeamId(null);
    }
}
