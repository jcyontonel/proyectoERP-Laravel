@props(['icon' => 'package', 'title', 'description' => null])

<div {{ $attributes->merge(['class' => 'px-4 py-12 text-center sm:px-5']) }}>
    <span class="mx-auto grid size-11 place-items-center rounded-full bg-slate-100 text-slate-400"><x-icon :name="$icon" class="size-5" /></span>
    <p class="mt-3 text-sm font-medium">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 text-xs text-slate-500">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-4 flex justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
