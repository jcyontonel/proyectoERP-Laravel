<?php

namespace App\Modulos\Nucleo\View\Composers;

use App\Modulos\Erp\Services\NotificacionService;
use App\Modulos\Plataforma\Services\SuscripcionService;
use App\Modulos\Nucleo\Support\EmpresaActual;
use App\Modulos\Nucleo\Support\Menu;
use Illuminate\View\View;

/** Datos que necesita layouts/app en todas las páginas */
class LayoutComposer
{
    public function __construct(private NotificacionService $notificaciones)
    {
    }

    public function compose(View $view): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $empresa   = EmpresaActual::empresa($user);
        $empresaId = $empresa?->id;

        $view->with([
            'usuario'        => $user,
            'rolActual'      => $user->rolActual() ?? 'Sin rol',
            'empresaActual'  => $empresa,
            'empresas'       => EmpresaActual::disponibles($user),
            'menu'           => Menu::para($user, $empresaId ? $this->notificaciones->contadores($empresaId, $user) : []),
            'alertas'        => $empresaId ? $this->notificaciones->alertas($empresaId, $user) : collect(),
            'cajaAbierta'    => $empresaId ? $this->notificaciones->cajaAbierta($empresaId, $user) : null,
            'suscripcion'    => $empresaId ? app(SuscripcionService::class)->resumen($empresaId) : null,
        ]);
    }
}
