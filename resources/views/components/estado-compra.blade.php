@props(['estado'])

@php
    $map = [
        'registrada' => ['success', 'Registrada'],
        'anulada'    => ['danger', 'Anulada'],
    ];
    [$tone, $label] = $map[$estado] ?? ['neutral', ucfirst($estado)];
@endphp

<x-badge :tone="$tone" :dot="true" {{ $attributes }}>{{ $label }}</x-badge>
