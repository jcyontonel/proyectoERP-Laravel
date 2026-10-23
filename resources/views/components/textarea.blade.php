@props(['name', 'label', 'value' => null, 'rows' => 3, 'hint' => null])

<div>
    <label for="{{ $name }}" class="field-label">{{ $label }}</label>
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}"
        {{ $attributes->class(['field resize-y', 'border-red-400 focus:border-red-500 focus:ring-red-500/20' => $errors->has($name)]) }}
        @if ($errors->has($name)) aria-invalid="true" @endif>{{ old($name, $value) }}</textarea>
    @if ($hint && ! $errors->has($name))
        <p class="mt-1.5 text-[11px] text-slate-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
