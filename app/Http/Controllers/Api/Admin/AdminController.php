<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Base de la API de administración (la protege el middleware TokenAdministracion) */
abstract class AdminController extends Controller
{
    /** Quién opera en el proyecto admin (cabecera X-Admin-Usuario), para los registros de pagos */
    protected function operador(Request $request): ?string
    {
        $nombre = trim((string) $request->header('X-Admin-Usuario'));

        return $nombre === '' ? null : Str::limit(strip_tags($nombre), 120, '');
    }
}
