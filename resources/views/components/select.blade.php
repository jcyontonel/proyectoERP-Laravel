@props(['label', 'name'])

<div>
    <label for="{{ $name }}" class="field-label">{{ $label }}</label>
    <div class="relative">
        <select id="{{ $name }}" name="{{ $name }}" {{ $attributes->class(['field appearance-none pr-9', 'border-red-400 focus:border-red-500 focus:ring-red-500/20' => $errors->has($name)]) }}>
            {{ $slot }}
        </select>
        <x-icon name="chevron-down" class="pointer-events-none absolute right-3 top-3 text-slate-400" />
    </div>
    @error($name)
        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
