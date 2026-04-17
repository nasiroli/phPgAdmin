@props([
    'compact' => false,
])

@php
    $setupNeeded = \Illuminate\Support\Facades\Route::has('setup')
        && app(\App\Services\AppSetupService::class)->isComplete() === false;
    $aboutUrl = config('app.about_url');
    $websiteUrl = config('app.website_url');
@endphp

@if ($compact)
    <div class="sticky top-0 z-20 flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-zinc-200/80 bg-zinc-50/95 px-4 py-2.5 text-sm backdrop-blur-md dark:border-white/[0.06] dark:bg-zinc-950/95">
        <span class="font-medium text-zinc-600 dark:text-zinc-400">Signed out</span>
        <a wire:navigate href="{{ route('login') }}" class="text-pg-blue-base hover:underline dark:text-pg-blue-light">Log in</a>
        @if ($setupNeeded)
            <a wire:navigate href="{{ route('setup') }}" class="text-zinc-700 hover:underline dark:text-zinc-300">First-time setup</a>
        @endif
        @if (is_string($aboutUrl) && $aboutUrl !== '')
            <a href="{{ $aboutUrl }}" target="_blank" rel="noopener noreferrer" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200">About</a>
        @endif
        @if (is_string($websiteUrl) && $websiteUrl !== '')
            <a href="{{ $websiteUrl }}" target="_blank" rel="noopener noreferrer" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200">Website</a>
        @endif
    </div>
@else
    <div class="mx-auto max-w-xl rounded-2xl border border-zinc-200/90 bg-white/90 p-8 text-center shadow-sm ring-1 ring-black/5 dark:border-white/[0.08] dark:bg-zinc-900/80 dark:ring-white/5 md:p-10">
        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-pg-blue-base/90 dark:text-pg-blue-light/90">{{ config('app.name') }}</p>
        <h2 class="mt-3 text-xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">Welcome</h2>
        <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">Sign in to manage servers, connections, and browse PostgreSQL. No redirect — use the links below.</p>
        <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
            <a wire:navigate href="{{ route('login') }}" class="ui-btn-primary inline-flex min-w-[10rem] justify-center px-5 py-2.5">
                {{ svg('hugeicons-link-forward', 'h-4 w-4 shrink-0') }}
                Log in
            </a>
            @if ($setupNeeded)
                <a wire:navigate href="{{ route('setup') }}" class="ui-btn-secondary inline-flex min-w-[10rem] justify-center px-5 py-2.5">
                    {{ svg('hugeicons-settings', 'h-4 w-4 shrink-0') }}
                    Setup
                </a>
            @endif
        </div>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-4 border-t border-zinc-200/80 pt-6 text-sm dark:border-white/[0.06]">
            @if (is_string($aboutUrl) && $aboutUrl !== '')
                <a href="{{ $aboutUrl }}" target="_blank" rel="noopener noreferrer" class="text-pg-blue-base hover:underline dark:text-pg-blue-light">About</a>
            @endif
            @if (is_string($websiteUrl) && $websiteUrl !== '')
                <a href="{{ $websiteUrl }}" target="_blank" rel="noopener noreferrer" class="text-pg-blue-base hover:underline dark:text-pg-blue-light">Website</a>
            @endif
        </div>
    </div>
@endif
