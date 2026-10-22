{{--
    Aviso del plan en todas las páginas (LayoutComposer → $suscripcion = SuscripcionService::resumen):
    suspendida/cancelada, vencida (en días de gracia), por vencer o cerca de un límite.
--}}
@php
    $s = $suscripcion['suscripcion'] ?? null;
    $estado = $suscripcion['estado'] ?? 'sin_plan';
    $aviso = null;

    if ($s) {
        $verPlan = auth()->user()->can('gestionar-empresas') ? route('config.suscripcion') : null;
        $gracia = (int) config('suscripciones.dias_gracia');
        $aviso = match (true) {
            $estado === 'cancelada'  => ['danger', 'La suscripción de la empresa fue cancelada: puedes consultar tu información, pero no registrar ventas, productos ni usuarios nuevos.'],
            $estado === 'suspendida' => ['danger', 'La suscripción está suspendida' . ($s->motivo_suspension ? " ({$s->motivo_suspension})" : ' por falta de pago') . ': puedes consultar tu información, pero no emitir comprobantes. Regulariza el pago para continuar.'],
            $estado === 'vencida'    => ['warning', 'Tu plan venció el ' . $s->vence_el->format('d/m/Y') . '. Tienes hasta el ' . $s->vence_el->copy()->addDays($gracia)->format('d/m/Y') . ' para pagar antes de que se suspenda la emisión.'],
            $suscripcion['dias'] !== null && $suscripcion['dias'] <= (int) config('suscripciones.dias_aviso')
                => ['info', ($estado === 'prueba' ? 'Tu periodo de prueba' : 'Tu plan ' . $s->plan->nombre) . ' vence ' . ($suscripcion['dias'] === 0 ? 'hoy' : "en {$suscripcion['dias']} " . ($suscripcion['dias'] === 1 ? 'día' : 'días')) . '.'],
            default => null,
        };

        // Sin aviso de vigencia: el límite más cercano a agotarse
        if (! $aviso) {
            $cerca = collect($suscripcion['limites'])->filter(fn ($l) => $l['maximo'] && $l['fraccion'] >= config('suscripciones.aviso_uso'))->sortByDesc('fraccion')->first();
            if ($cerca) {
                $aviso = [$cerca['usado'] >= $cerca['maximo'] ? 'danger' : 'warning',
                    "Usaste {$cerca['usado']} de {$cerca['maximo']} {$cerca['etiqueta']} de tu plan {$s->plan->nombre}."
                    . ($cerca['usado'] >= $cerca['maximo'] ? ' Llegaste al límite: cambia a un plan superior para continuar.' : '')];
            }
        }
    }
@endphp

@if ($aviso)
    <x-alert :tone="$aviso[0]" class="mb-5">
        {{ $aviso[1] }}
        @if ($verPlan)
            <a href="{{ $verPlan }}" class="ml-1 font-semibold underline">Ver mi plan</a>
        @endif
    </x-alert>
@endif
