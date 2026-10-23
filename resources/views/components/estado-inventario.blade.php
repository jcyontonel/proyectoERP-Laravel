{{-- Estado de ajustes y transferencias --}}
@props(['estado'])

@php
    $map = [
        'borrador'    => ['neutral', 'Borrador'],
        'pendiente'   => ['warning', 'Por aprobar'],
        'confirmado'  => ['success', 'Confirmado'],
        'en_transito' => ['info', 'En tránsito'],
        'recibida'    => ['success', 'Recibida'],
        'anulado'     => ['danger', 'Anulado'],
        'anulada'     => ['danger', 'Anulada'],
    ];
    [$tone, $label] = $map[$estado] ?? ['neutral', ucfirst((string) $estado)];
@endphp

<x-badge :tone="$tone" :dot="true" {{ $attributes }}>{{ $label }}</x-badge>
