<?php

use App\Http\Requests\StoreServerRequest;
use App\Http\Requests\UpdateServerRequest;
use App\Models\Server;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Server')] class extends Component
{
    public string $name = 'main';

    public string $host = 'localhost';

    public int $port = 5432;

    public string $notes = 'Main server';

    public ?Server $server = null;

    public function mount(?Server $server = null): void
    {
        $this->server = $server;
        $this->name = $server?->name ?? 'main';
        $this->host = $server?->host ?? 'localhost';
        $this->port = $server?->port ?? 5432;
        $this->notes = $server?->notes ?? 'My main server';
    }

    public function save(): void
    {
        if ($this->server) {
            $this->validate((new UpdateServerRequest)->rules());
            $this->server->update([
                'name' => $this->name,
                'host' => $this->host,
                'port' => $this->port,
                'notes' => $this->notes ?: null,
            ]);
        } else {
            $this->validate((new StoreServerRequest)->rules());
            Server::query()->create([
                'name' => $this->name,
                'host' => $this->host,
                'port' => $this->port,
                'notes' => $this->notes ?: null,
            ]);
        }

        $this->dispatch('sidebar-refresh');
        $this->redirect(url('/'), navigate: true);
    }
};
?>

@php
    $heading = $server ? 'Edit server' : 'New server';
@endphp

<div class="mx-auto max-w-lg">
    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-emerald-500/90">Servers</p>
    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-zinc-50">{{ $heading }}</h1>

    <form wire:submit="save" class="ui-surface mt-8 space-y-5 p-6 md:p-8">
        <div class="space-y-2">
            <label class="ui-label">Name</label>
            <input type="text" wire:model="name" class="ui-field" autocomplete="off" />
            @error('name') <p class="text-sm text-red-400/95">{{ $message }}</p> @enderror
        </div>
        <div class="space-y-2">
            <label class="ui-label">Host</label>
            <input type="text" wire:model="host" class="ui-field" autocomplete="off" />
            @error('host') <p class="text-sm text-red-400/95">{{ $message }}</p> @enderror
        </div>
        <div class="space-y-2">
            <label class="ui-label">Port</label>
            <input type="number" wire:model="port" class="ui-field" />
            @error('port') <p class="text-sm text-red-400/95">{{ $message }}</p> @enderror
        </div>
        <div class="space-y-2">
            <label class="ui-label">Notes</label>
            <textarea wire:model="notes" rows="3" class="ui-field min-h-[5rem] resize-y"></textarea>
            @error('notes') <p class="text-sm text-red-400/95">{{ $message }}</p> @enderror
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
