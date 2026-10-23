<?php

namespace App\Modulos\Plataforma\Console;

use App\Modulos\Nucleo\Models\User;
use Illuminate\Console\Command;

/**
 * Da (o quita) el permiso global de administrar la plataforma a un usuario existente.
 * Es la única forma de asignarlo: no hay correos fijos en el código ni pantalla que lo otorgue.
 */
class AsignarAdmin extends Command
{
    protected $signature = 'plataforma:asignar-admin {correo : Correo de un usuario existente} {--quitar : Le quita el permiso}';

    protected $description = 'Asigna o quita el permiso de administrar la plataforma';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower(trim($this->argument('correo'))))->first();
        if (! $user) {
            $this->error('No hay un usuario con ese correo.');

            return self::FAILURE;
        }
        if (! $this->option('quitar') && ! $user->activo) {
            $this->error('El usuario está desactivado.');

            return self::FAILURE;
        }

        $user->forceFill(['administra_plataforma' => ! $this->option('quitar')])->save();

        $this->info($this->option('quitar')
            ? "{$user->email} ya no administra la plataforma."
            : "{$user->email} ahora administra la plataforma (entra en /plataforma con su usuario del ERP).");

        return self::SUCCESS;
    }
}
