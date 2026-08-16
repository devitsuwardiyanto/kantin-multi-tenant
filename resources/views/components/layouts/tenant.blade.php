@props(['title' => 'Tenant'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
    <div x-data="{ open: false }" class="flex min-h-screen">
        <aside :class="open ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-20 w-64 transform border-r border-zinc-200 bg-white p-4 transition-transform duration-200 lg:static lg:translate-x-0 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mb-6 text-lg font-semibold">Tenant</div>
            <nav class="flex flex-col gap-1 text-sm">
                <span class="rounded-lg bg-zinc-100 px-3 py-2 font-medium dark:bg-zinc-800">Dashboard</span>
                <span class="px-3 py-2 text-zinc-400">Katalog (Modul 7)</span>
                <span class="px-3 py-2 text-zinc-400">Pesanan / KDS (Modul 12)</span>
            </nav>
        </aside>
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex min-h-14 items-center gap-3 border-b border-zinc-200 bg-white px-4 dark:border-zinc-800 dark:bg-zinc-900">
                <button type="button" @click="open = !open" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg lg:hidden" aria-label="Toggle sidebar">☰</button>
                <span class="font-semibold">{{ $title }}</span>
                @isset($headerRight)<div class="ms-auto">{{ $headerRight }}</div>@endisset
            </header>
            <main class="flex-1 p-6">{{ $slot }}</main>
        </div>
    </div>
    @fluxScripts
</body>
</html>
