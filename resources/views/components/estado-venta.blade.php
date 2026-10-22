@props(['estado'])

@php
    $map = [
        'registrada' => ['success', 'Completada'],
        'completada' => ['success', 'Completada'],
        'borrador'   => ['neutral', 'Borrador'],
        'anulada'    => ['danger', 'Anulada'],
    ];
    [$tone, $label] = $map[$estado] ?? ['neutral', ucfirst($estado)];
@endphp

<x-badge :tone="$tone" :dot="true" {{ $attributes }}>{{ $label }}</x-badge>
