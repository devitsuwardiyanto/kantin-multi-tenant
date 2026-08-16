@props(['title' => 'Admin'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
    <div class="flex min-h-screen flex-col">
        <header class="flex min-h-14 items-center gap-3 border-b border-zinc-200 bg-white px-6 dark:border-zinc-800 dark:bg-zinc-900">
            <span class="text-lg font-semibold">Pengelola Kantin</span>
            <span class="ms-2 text-sm text-zinc-500">/ {{ $title }}</span>
            @isset($headerRight)<div class="ms-auto">{{ $headerRight }}</div>@endisset
        </header>
        <main class="mx-auto w-full max-w-6xl flex-1 p-6">{{ $slot }}</main>
    </div>
    @fluxScripts
</body>
</html>
