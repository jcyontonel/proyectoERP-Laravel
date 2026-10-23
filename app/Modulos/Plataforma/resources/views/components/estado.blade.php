{{-- Badge de estado: <x-plataforma::estado :valor="$s['estado_efectivo']" /> · tipo: suscripcion | pago --}}
@props(['valor' => null, 'tipo' => 'suscripcion'])

@php
    [$tono, $etiqueta] = config($tipo === 'pago' ? 'panel.estados_pago' : 'panel.estados')[$valor ?? 'sin_plan'] ?? ['neutral', ucfirst((string) $valor)];
@endphp

<x-plataforma::badge :tone="$tono" shape="box" dot {{ $attributes }}>{{ $etiqueta }}</x-plataforma::badge>
