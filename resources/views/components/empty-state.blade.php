@props(['title' => 'Belum ada data', 'description' => null])
<div {{ $attributes->class('flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-700') }}>
    <p class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $title }}</p>
    @if ($description)
        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-2">{{ $action }}</div>
    @endisset
</div>
