<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <title>{{ $title ?? config('app.name') }}</title>

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

<body class="min-h-screen bg-zinc-50 bg-gradient-to-b from-white via-zinc-50 to-zinc-100 text-zinc-900 antialiased dark:bg-zinc-950 dark:bg-gradient-to-b dark:from-zinc-900/50 dark:via-zinc-950 dark:to-zinc-950 dark:text-zinc-100" style="padding-top: env(safe-area-inset-top)">
    <div class="pointer-events-none fixed right-3 z-[100] md:right-5" style="top: calc(env(safe-area-inset-top, 0px) + 0.75rem)">
        <div class="pointer-events-auto">
            <x-theme-toggle />
        </div>
    </div>

    <div class="flex min-h-screen" x-data="sidebarShell()">
        <div class="hidden md:flex md:shrink-0">
            <aside
                class="relative flex min-h-screen flex-col border-r border-zinc-200/90 bg-white/70 backdrop-blur-sm transition-[width] duration-200 ease-out dark:border-white/[0.06] dark:bg-zinc-900/50"
                :style="collapsed ? { width: '3.25rem' } : { width: widthPx + 'px' }"
            >
                <div x-show="!collapsed" x-cloak class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden p-3">
                    <div class="mb-4 shrink-0 text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-emerald-600/95 dark:text-emerald-500/90">{{ config('app.name') }}</div>
                    <livewire:sidebar-nav />
                </div>

                <div x-show="collapsed" x-cloak class="flex flex-col items-center gap-3 py-3">
                    <a
                        wire:navigate
                        href="{{ url('/') }}"
                        class="inline-flex rounded-xl p-2 text-emerald-600/95 transition hover:bg-zinc-200/60 dark:text-emerald-400/90 dark:hover:bg-white/5"
                        title="Dashboard"
                    >
                        {{ svg('hugeicons-dashboard-square-01', 'h-5 w-5 shrink-0') }}
                    </a>
                    <span class="inline-flex rounded-xl p-2 text-zinc-400 dark:text-zinc-600" title="Expand for object browser">
                        {{ svg('hugeicons-sidebar-left', 'h-5 w-5 shrink-0') }}
                    </span>
                </div>

                <button
                    type="button"
                    @click="toggle()"
                    class="absolute top-1/2 right-0 z-30 flex h-9 w-9 translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-zinc-200 bg-white text-zinc-700 shadow-md ring-1 ring-black/5 backdrop-blur-sm transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-white/10 dark:bg-zinc-800/90 dark:text-zinc-200 dark:ring-white/5 dark:hover:border-white/15 dark:hover:bg-zinc-700/90"
                    :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                >
                    <span x-show="!collapsed" class="inline-flex" x-cloak>{{ svg('hugeicons-panel-left-close', 'h-4 w-4 shrink-0') }}</span>
                    <span x-show="collapsed" class="inline-flex" x-cloak>{{ svg('hugeicons-panel-left-open', 'h-4 w-4 shrink-0') }}</span>
                </button>
            </aside>

            <div
                x-show="!collapsed"
                x-cloak
                class="w-1.5 shrink-0 cursor-col-resize hover:bg-emerald-500/25 active:bg-emerald-500/40"
                title="Drag to resize sidebar"
                @mousedown.prevent="startResize($event)"
            ></div>
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex items-center justify-between border-b border-zinc-200/90 bg-white/60 px-4 py-3 backdrop-blur-md dark:border-white/[0.06] dark:bg-zinc-950/40 md:hidden">
                <span class="text-sm font-semibold tracking-tight text-emerald-600/95 dark:text-emerald-400/95">{{ config('app.name') }}</span>
                <a wire:navigate href="{{ url('/') }}" class="inline-flex items-center gap-2 rounded-xl px-3 py-1.5 text-sm text-zinc-600 transition hover:bg-zinc-200/60 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-zinc-100">
                    {{ svg('hugeicons-dashboard-square-01', 'h-4 w-4 shrink-0') }}
                    Home
                </a>
            </header>

            <main class="min-w-0 flex-1 overflow-x-auto px-4 py-6 md:px-8 md:py-8 lg:px-10">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts

    <script id="__nb-shell-config" type="application/json">@json($shellConfig ?? [])</script>
</body>
</html>
