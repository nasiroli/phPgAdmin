<?php

use App\Http\Requests\StoreConnectionRequest;
use App\Http\Requests\UpdateConnectionRequest;
use App\Models\Connection;
use App\Models\Server;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;
use NativeBlade\Facades\NativeBlade;

new #[Title('Connection')] class extends Component
{
    public bool $isAuthenticated = false;

    public ?int $server_id = null;

    /** Readable label for server datalist; must match option text exactly when picking. */
    public string $serverDatalistLabel = '';

    public string $database = 'postgres';

    public string $username = 'postgres';

    public string $password = 'password';

    public string $sslmode = 'prefer';

    public ?Connection $connection = null;

    /** @var Collection<int, Server> */
    public Collection $servers;

    public function mount(?Connection $connection = null): void
    {
        $this->isAuthenticated = NativeBlade::getState('auth.user') !== null;
        $this->connection = $connection;
        $this->servers = collect();
        if (! $this->isAuthenticated) {
            return;
        }
        $this->servers = Server::query()->orderBy('name')->get();

        $this->server_id = $connection?->server_id ?? null;
        $this->database = $connection?->database ?? 'postgres';
        $this->username = $connection?->username ?? 'postgres';
        if ($connection) {
            $plain = $connection->tryDecryptPassword();
            $this->password = $plain === null ? '' : $plain;
            if ($plain === null) {
                session()->flash('conn_decrypt', 'Stored password could not be decrypted (for example after APP_KEY changed). Enter the password again and save.');
            }
        } else {
            $this->password = 'password';
        }
        $this->sslmode = $connection?->sslmode ?? 'prefer';
        $this->syncServerDatalistLabelFromId();
    }

    public function updatedServerDatalistLabel(): void
    {
        $trim = trim($this->serverDatalistLabel);
        $match = $this->servers->first(fn (Server $s) => $this->formatServerOptionLabel($s) === $trim);
        if ($match !== null) {
            $this->server_id = (int) $match->id;
        }
    }

    public function syncServerDatalistLabelFromId(): void
    {
        $s = $this->servers->firstWhere('id', $this->server_id);
        $this->serverDatalistLabel = $s !== null ? $this->formatServerOptionLabel($s) : '';
    }

    public function formatServerOptionLabel(Server $server): string
    {
        return "{$server->name} ({$server->host})";
    }

    public function updatedSslmode(): void
    {
        $allowed = ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'];
        if (! in_array($this->sslmode, $allowed, true)) {
            $this->sslmode = 'prefer';
        }
    }

    public function save(): void
    {
        if (! $this->isAuthenticated) {
            return;
        }
        if ($this->connection) {
            $rules = (new UpdateConnectionRequest)->rules();
            $this->validate($rules);
            $data = [
                'server_id' => $this->server_id,
                'database' => $this->database,
                'username' => $this->username,
                'sslmode' => $this->sslmode,
            ];
            if ($this->password !== '') {
                $data['password'] = $this->password;
            }
            $this->connection->update($data);
        } else {
            $this->validate((new StoreConnectionRequest)->rules());
            Connection::query()->create([
                'server_id' => $this->server_id,
                'database' => $this->database,
                'username' => $this->username,
                'password' => $this->password,
                'sslmode' => $this->sslmode,
            ]);
        }

        $this->dispatch('sidebar-refresh');
        $this->redirect(url('/'), navigate: true);
    }
};
?>

@php
    $heading = $connection ? 'Edit connection' : 'New connection';
@endphp

@if (! $isAuthenticated)
    <div class="mx-auto max-w-md space-y-4 text-center">
        <p class="text-sm text-zinc-600 dark:text-zinc-400">Sign in to manage connection profiles.</p>
        <a wire:navigate href="{{ route('login') }}" class="ui-btn-primary inline-flex justify-center px-6 py-2.5">Log in</a>
    </div>
@else
<div class="mx-auto max-w-lg">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-pg-blue-base/90 dark:text-pg-blue-light/90">Connections</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">{{ $heading }}</h1>
        </div>
        <div class="shrink-0 pt-0.5">
            <x-theme-toggle />
        </div>
    </div>

    <form wire:submit="save" class="ui-surface mt-8 space-y-5 p-6 md:p-8">
        @if (session('conn_decrypt'))
            <div class="rounded-xl border border-amber-500/30 bg-amber-950/30 px-4 py-3 text-sm text-amber-100/95" role="status">
                {{ session('conn_decrypt') }}
            </div>
        @endif
        <div class="space-y-2">
            <label class="ui-label" for="conn-server-input">Server</label>
            <input
                id="conn-server-input"
                type="text"
                list="conn-server-datalist"
                wire:model.live.debounce.300ms="serverDatalistLabel"
                wire:blur="syncServerDatalistLabelFromId"
                class="ui-combobox"
                autocomplete="off"
                autocapitalize="off"
                spellcheck="false"
                placeholder="Choose server…"
            />
            <datalist id="conn-server-datalist">
                @foreach ($servers as $s)
                    <option value="{{ $this->formatServerOptionLabel($s) }}"></option>
                @endforeach
            </datalist>
            @error('server_id') <p class="text-sm text-red-400/95">{{ $message }}</p> @enderror
            @if ($servers->isEmpty())
                <p class="text-sm text-pg-orange-light/90">Create a server first.</p>
            @endif
        </div>
        <div class="space-y-2">
            <label class="ui-label">Database</label>
            <input type="text" wire:model="database" class="ui-field" autocomplete="off" />
            @error('database') <p class="text-sm text-red-400/95">{{ $message }}</p> @enderror
        </div>
        <div class="space-y-2">
            <label class="ui-label">Username</label>
            <input type="text" wire:model="username" class="ui-field" autocomplete="off" />
            @error('username') <p class="text-sm text-red-400/95">{{ $message }}</p> @enderror
        </div>
        <div class="space-y-2">
            <label class="ui-label">Password {{ $connection ? '(leave blank to keep)' : '' }}</label>
            <input type="password" wire:model="password" class="ui-field" autocomplete="off" />
            @error('password') <p class="text-sm text-red-400/95">{{ $message }}</p> @enderror
        </div>
        <div class="space-y-2">
            <label class="ui-label" for="conn-ssl-input">SSL mode</label>
            <input
                id="conn-ssl-input"
                type="text"
                list="conn-ssl-datalist"
                wire:model.live.debounce.200ms="sslmode"
                class="ui-combobox"
                autocomplete="off"
                autocapitalize="off"
                spellcheck="false"
            />
            <datalist id="conn-ssl-datalist">
                @foreach (['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'] as $mode)
                    <option value="{{ $mode }}"></option>
                @endforeach
            </datalist>
            @error('sslmode') <p class="text-sm text-red-400/95">{{ $message }}</p> @enderror
        </div>
        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="ui-btn-primary">
                {{ svg('hugeicons-floppy-disk', 'h-4 w-4 shrink-0') }}
                Save
            </button>
            <a wire:navigate href="{{ url('/') }}" class="ui-btn-secondary">
                {{ svg('hugeicons-cancel-01', 'h-4 w-4 shrink-0') }}
                Cancel
            </a>
        </div>
    </form>
</div>
@endif
