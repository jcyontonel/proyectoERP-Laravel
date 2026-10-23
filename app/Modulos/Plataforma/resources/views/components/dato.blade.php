@props(['label'])

<div class="flex items-start justify-between gap-4 py-2.5 text-sm">
    <dt class="shrink-0 text-slate-500">{{ $label }}</dt>
    <dd class="min-w-0 text-right font-medium">{{ $slot }}</dd>
</div>
