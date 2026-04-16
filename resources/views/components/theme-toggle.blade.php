<div
    class="relative"
    x-data="themePicker()"
    x-cloak
    @keydown.escape.window="menuOpen = false"
>
    <button
        type="button"
        @click="menuOpen = !menuOpen"
        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200/90 bg-white text-zinc-700 shadow-sm ring-1 ring-black/5 transition hover:bg-zinc-50 dark:border-white/10 dark:bg-zinc-800/90 dark:text-zinc-200 dark:ring-white/5 dark:hover:bg-zinc-700/90"
        :aria-expanded="menuOpen"
        aria-haspopup="true"
        aria-label="Color theme"
    >
        <span x-show="mode === 'system'" class="inline-flex">{{ svg('hugeicons-computer', 'h-5 w-5 shrink-0') }}</span>
        <span x-show="mode === 'light'" class="inline-flex">{{ svg('hugeicons-sun', 'h-5 w-5 shrink-0') }}</span>
        <span x-show="mode === 'dark'" class="inline-flex">{{ svg('hugeicons-moon', 'h-5 w-5 shrink-0') }}</span>
    </button>
    <div
        x-show="menuOpen"
        x-transition
        x-cloak
        @click.outside="menuOpen = false"
        class="absolute right-0 z-[200] mt-2 w-44 overflow-hidden rounded-xl border border-zinc-200 bg-white py-1 shadow-lg ring-1 ring-black/5 dark:border-white/10 dark:bg-zinc-900 dark:ring-white/5"
        role="menu"
    >
        <button
            type="button"
            role="menuitem"
            @click="setMode('system')"
            class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-zinc-800 transition hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800/80"
            :class="mode === 'system' ? 'bg-zinc-100 dark:bg-zinc-800/80' : ''"
        >
            {{ svg('hugeicons-computer', 'h-4 w-4 shrink-0 text-zinc-500') }}
            System
        </button>
        <button
            type="button"
            role="menuitem"
            @click="setMode('light')"
            class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-zinc-800 transition hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800/80"
            :class="mode === 'light' ? 'bg-zinc-100 dark:bg-zinc-800/80' : ''"
        >
            {{ svg('hugeicons-sun', 'h-4 w-4 shrink-0 text-pg-orange-light') }}
            Light
        </button>
        <button
            type="button"
            role="menuitem"
            @click="setMode('dark')"
            class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-zinc-800 transition hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800/80"
            :class="mode === 'dark' ? 'bg-zinc-100 dark:bg-zinc-800/80' : ''"
        >
            {{ svg('hugeicons-moon', 'h-4 w-4 shrink-0 text-pg-blue-dark') }}
            Dark
        </button>
    </div>
</div>
