<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use NativeBlade\Facades\NativeBlade;

new #[Layout('components.layouts.auth')] class extends Component
{
    public string $password = '';

    public string $error = '';

    public function mount(): void
    {
        NativeBlade::forget('auth.user');
    }

    public function login()
    {
        $this->error = '';

        if ($this->password === config('app.desktop_password')) {
            NativeBlade::setState('auth.user', [
                'name' => 'Operator',
            ]);

            return $this->redirect('/', navigate: true);
        }

        $this->error = 'Invalid password';

        return null;
    }
};
?>

<div class="flex min-h-screen w-full flex-col items-center justify-center p-6">
    <div class="ui-surface w-full max-w-md space-y-8 p-8 md:p-10">
        <div class="text-center">
            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.25em] text-pg-blue-base/90 dark:text-pg-blue-light/90">{{ config('app.name') }}</p>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">Sign in</h1>
            <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">Enter the desktop password to manage PostgreSQL connections.</p>
        </div>

        @if ($error)
            <div class="rounded-xl border border-red-500/20 bg-red-950/35 px-4 py-3 text-sm text-red-200/95">{{ $error }}</div>
        @endif

        <div class="space-y-4">
            <div class="space-y-2">
                <label class="ui-label">Password</label>
                <input
                    type="password"
                    wire:model="password"
                    placeholder="••••••••"
                    class="ui-field"
                    autocomplete="off"
                />
            </div>
        </div>

        <button
            type="button"
            wire:click="login"
            class="ui-btn-primary w-full py-3"
        >
            Unlock
        </button>

        <p class="text-center text-xs leading-relaxed text-zinc-600 dark:text-zinc-500">
            Configure <span class="rounded bg-zinc-200/90 px-1.5 py-0.5 font-mono text-[0.7rem] text-zinc-700 dark:bg-zinc-800/80 dark:text-zinc-400">APP_DESKTOP_PASSWORD</span> in your environment.
        </p>
    </div>
</div>
