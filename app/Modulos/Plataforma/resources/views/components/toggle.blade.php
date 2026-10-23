{{-- Interruptor accesible (checkbox). Envía 1/0: el hidden garantiza el 0 cuando está apagado --}}
@props(['name', 'label', 'checked' => false, 'hint' => null, 'disabled' => false])

<label {{ $attributes->class(['flex items-start gap-3', 'cursor-pointer' => ! $disabled, 'cursor-not-allowed opacity-60' => $disabled]) }}>
    <input type="hidden" name="{{ $name }}" value="0" @disabled($disabled)>
    <input type="checkbox" name="{{ $name }}" value="1" class="peer sr-only" @checked(old($name, $checked)) @disabled($disabled)>
    <span class="relative mt-0.5 h-5 w-9 shrink-0 rounded-full bg-slate-200 transition after:absolute after:left-0.5 after:top-0.5 after:size-4 after:rounded-full after:bg-white after:shadow after:transition peer-checked:bg-brand-600 peer-checked:after:translate-x-4 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40"></span>
    <span class="leading-tight">
        <span class="block text-sm font-medium">{{ $label }}</span>
        @if ($hint)<span class="mt-0.5 block text-xs text-slate-500">{{ $hint }}</span>@endif
    </span>
</label>
