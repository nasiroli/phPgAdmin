<?php

use App\Services\AppSetupService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.auth')] class extends Component
{
    public string $password = '';

    public string $password_confirmation = '';

    public string $error = '';

    public function mount(AppSetupService $setup): void
    {
        if ($setup->isComplete()) {
            $this->redirect(route('login'), navigate: true);
        }
    }

    public function save(AppSetupService $setup): void
    {
        $this->error = '';
        $this->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $result = $setup->run($this->password);
            session()->flash('setup_npm_ok', $result['npm_build_ok']);
            if (! $result['npm_build_ok'] && $result['npm_build_message']) {
                session()->flash('setup_npm_message', $result['npm_build_message']);
            }

            $this->redirect(route('login'), navigate: true);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }
};
?>

<div class="flex min-h-screen w-full flex-col items-center justify-center p-6">
    <div class="ui-surface w-full max-w-md space-y-8 p-8 md:p-10">
        <div class="text-center">
            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.25em] text-pg-blue-base/90 dark:text-pg-blue-light/90">{{ config('app.name') }}</p>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">First-time setup</h1>
            <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                Choose a password to unlock the app. It is stored only in your local <span class="font-mono text-xs">.env</span> file (not in <span class="font-mono text-xs">.env.example</span>).
            </p>
        </div>

        @if ($error)
            <div class="rounded-xl border border-red-500/20 bg-red-950/35 px-4 py-3 text-sm text-red-200/95">{{ $error }}</div>
        @endif

        <div class="space-y-4">
            <div class="space-y-2">
                <label class="ui-label" for="setup-password">Password</label>
                <input
                    id="setup-password"
                    type="password"
                    wire:model="password"
                    class="ui-field"
                    autocomplete="new-password"
                    placeholder="At least 8 characters"
                />
                @error('password')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div class="space-y-2">
                <label class="ui-label" for="setup-password-confirm">Confirm password</label>
                <input
                    id="setup-password-confirm"
                    type="password"
                    wire:model="password_confirmation"
                    class="ui-field"
                    autocomplete="new-password"
                />
            </div>
        </div>

        <button
            type="button"
            wire:click="save"
            wire:loading.attr="disabled"
            class="ui-btn-primary w-full py-3 disabled:opacity-60"
        >
            <span wire:loading.remove wire:target="save">Finish setup</span>
            <span wire:loading wire:target="save">Working…</span>
        </button>

        <p class="text-center text-xs leading-relaxed text-zinc-600 dark:text-zinc-500">
            This runs database migrations, generates <span class="font-mono">APP_KEY</span>, configures NativeBlade, and attempts <span class="font-mono">npm run build</span>.
        </p>
    </div>
</div>
