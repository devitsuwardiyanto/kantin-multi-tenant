@props(['title' => 'Pelanggan'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col">
        <header class="sticky top-0 z-10 flex min-h-14 items-center gap-2 border-b border-zinc-200 bg-white/90 px-4 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/90">
            <span class="text-base font-semibold">{{ $title }}</span>
            @isset($headerRight)<div class="ms-auto">{{ $headerRight }}</div>@endisset
        </header>
        <main class="flex-1 p-4">{{ $slot }}</main>
        <footer class="border-t border-zinc-200 p-4 text-center text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
            {{ config('app.name') }}
        </footer>
    </div>
    @fluxScripts
</body>
</html>
