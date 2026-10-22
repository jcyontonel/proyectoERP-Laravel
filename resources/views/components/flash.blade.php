{{-- Mensajes de sesión (status / error) y el primer error de validación --}}
@foreach (['status' => 'success', 'error' => 'danger'] as $clave => $tono)
    @if (session($clave))
        <x-alert :tone="$tono" {{ $attributes->merge(['class' => 'mt-4']) }}>{{ session($clave) }}</x-alert>
    @endif
@endforeach

@if ($errors->any())
    <x-alert tone="danger" {{ $attributes->merge(['class' => 'mt-4']) }}>
        {{ $errors->count() === 1 ? $errors->first() : 'Revisa los campos marcados: hay ' . $errors->count() . ' errores.' }}
    </x-alert>
@endif
