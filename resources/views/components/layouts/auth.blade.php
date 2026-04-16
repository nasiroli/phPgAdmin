<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ $title ?? 'Laravel' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script>
        (function () {
            try {
                var k = 'phppgadmin.theme';
                var s = null;
                try {
                    s = localStorage.getItem(k);
                } catch (e) {}
                if (s !== 'light' && s !== 'dark' && s !== 'system') {
                    s = 'system';
                }
                var dark =
                    s === 'dark' ||
                    (s === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>:root{--nb-safe-top:env(safe-area-inset-top,0px);--nb-safe-bottom:env(safe-area-inset-bottom,0px)}</style>
</head>
<body class="relative flex min-h-screen items-center justify-center bg-zinc-50 bg-gradient-to-b from-white via-zinc-50 to-zinc-100 text-zinc-900 antialiased dark:bg-zinc-950 dark:bg-gradient-to-b dark:from-zinc-900/40 dark:via-zinc-950 dark:to-zinc-950 dark:text-zinc-100">

    <div class="pointer-events-none fixed right-3 z-[100] md:right-5" style="top: calc(env(safe-area-inset-top, 0px) + 0.75rem)">
        <div class="pointer-events-auto">
            <x-theme-toggle />
        </div>
    </div>

    {{ $slot }}

    @livewireScripts

    <script id="__nb-shell-config" type="application/json">@json($shellConfig ?? [])</script>
</body>
</html>
