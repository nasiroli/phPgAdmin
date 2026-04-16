<?php

use App\Http\Requests\StoreConnectionRequest;
use App\Http\Requests\UpdateConnectionRequest;
use App\Models\Connection;
use App\Models\Server;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Connection')] class extends Component
{
    public ?int $server_id = null;

    public string $database = 'posgres';

    public string $username = 'postgres';

    public string $password = 'password';

    public string $sslmode = 'prefer';

    public ?Connection $connection = null;

    /** @var Collection<int, Server> */
    public Collection $servers;

    public function mount(?Connection $connection = null): void
    {
        $this->connection = $connection;
        $this->servers = Server::query()->orderBy('name')->get();

        $this->server_id = $connection?->server_id ?? null;
        $this->database = $connection?->database ?? 'postgres';
        $this->username = $connection?->username ?? 'postgres';
        $this->password = $connection?->password ?? 'password';
        $this->sslmode = $connection?->sslmode ?? 'prefer';
    }

    public function save(): void
    {
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

<div class="mx-auto max-w-lg">
    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-emerald-500/90">Connections</p>
    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-zinc-50">{{ $heading }}</h1>

    <form wire:submit="save" class="ui-surface mt-8 space-y-5 p-6 md:p-8">
        <div class="space-y-2">
            <label class="ui-label">Server</label>
            <select wire:model="server_id" class="ui-select">
                <option value="">Choose server…</option>
                @foreach ($servers as $s)
                    <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->host }})</option>
                @endforeach
            </select>
            @error('server_id') <p class="text-sm text-red-400/95">{{ $message }}</p> @enderror
            @if ($servers->isEmpty())
                <p class="text-sm text-amber-400/90">Create a server first.</p>
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
            <label class="ui-label">SSL mode</label>
            <select wire:model="sslmode" class="ui-select">
                <option value="disable">disable</option>
                <option value="allow">allow</option>
                <option value="prefer">prefer</option>
                <option value="require">require</option>
                <option value="verify-ca">verify-ca</option>
                <option value="verify-full">verify-full</option>
            </select>
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
