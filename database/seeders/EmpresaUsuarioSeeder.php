<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use App\Models\Empresa;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class UsuarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $empresas = Empresa::all();
        $roles = Role::all();

        foreach ($empresas as $empresa) {
            foreach ($roles as $rol) {
                User::create([
                    //'empresa_id' => $empresa->id,
                    'name' => $rol->nombre . ' ' . $faker->firstName,
                    'email' => strtolower($rol->name) . $empresa->id . '@erp.com',
                    'password' => Hash::make('password'),
                ])->assignRole($rol->name);
            }
        }

        // Usuario Admin general
        User::create([
            //'empresa_id' => $empresas->first()->id,
            'name' => 'Admin General',
            'email' => 'admin@erp.com',
            'password' => Hash::make('admin123'),
        ])->assignRole('Administrador');
    }
}
