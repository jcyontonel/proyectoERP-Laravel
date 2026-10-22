@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null])

<div>
    <label for="{{ $name }}" class="field-label">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}"
        {{ $attributes->class(['field', 'border-red-400 focus:border-red-500 focus:ring-red-500/20' => $errors->has($name)]) }}
        @if ($errors->has($name)) aria-invalid="true" @endif>
    @if ($hint && ! $errors->has($name))
        <p class="mt-1.5 text-[11px] text-slate-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
