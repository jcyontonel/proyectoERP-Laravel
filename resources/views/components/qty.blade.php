@props(['name' => null, 'value' => 1, 'min' => 1, 'max' => null])

<div data-qty {{ $attributes->merge(['class' => 'inline-flex h-8 items-center rounded-lg border border-slate-200 bg-white focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20']) }}>
    <button type="button" data-qty-minus aria-label="Disminuir cantidad" class="grid size-8 place-items-center rounded-l-lg text-slate-500 hover:bg-slate-50 hover:text-brand-600">
        <x-icon name="minus" class="size-3.5" />
    </button>
    <input type="number" data-qty-input @if ($name) name="{{ $name }}" @endif value="{{ $value }}" min="{{ $min }}" @if ($max) max="{{ $max }}" @endif inputmode="numeric" aria-label="Cantidad"
        class="w-14 border-0 bg-transparent p-0 text-center text-sm font-medium text-ink [appearance:textfield] focus:outline-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
    <button type="button" data-qty-plus aria-label="Aumentar cantidad" class="grid size-8 place-items-center rounded-r-lg text-slate-500 hover:bg-slate-50 hover:text-brand-600">
        <x-icon name="plus" class="size-3.5" />
    </button>
</div>