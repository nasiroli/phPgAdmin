<?php

use App\Models\Connection;
use App\Models\Server;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Connections')] class extends Component
{
    /** @var Collection<int, Server> */
    public Collection $servers;

    public ?int $selectedServerId = null;

    public ?int $selectedConnectionId = null;

    public function mount(): void
    {
        $this->loadServers();
    }

    public function loadServers(): void
    {
        $this->servers = Server::query()->with('connections')->orderBy('name')->get();
        $this->syncSelectionAfterLoad();
    }

    public function updatedSelectedServerId(): void
    {
        $server = $this->servers->firstWhere('id', $this->selectedServerId);
        $this->selectedConnectionId = $server?->connections->sortBy('database')->first()?->id;
    }

    public function deleteConnection(int $id): void
    {
        Connection::query()->whereKey($id)->delete();
        $this->loadServers();
        $this->dispatch('sidebar-refresh');
    }

    public function deleteServer(int $id): void
    {
        Server::query()->whereKey($id)->delete();
        $this->loadServers();
        $this->dispatch('sidebar-refresh');
    }

    private function syncSelectionAfterLoad(): void
    {
        if ($this->servers->isEmpty()) {
            $this->selectedServerId = null;
            $this->selectedConnectionId = null;

            return;
        }

        if ($this->selectedServerId === null || $this->servers->firstWhere('id', $this->selectedServerId) === null) {
            $this->selectedServerId = (int) $this->servers->first()->id;
        }

        $server = $this->servers->firstWhere('id', $this->selectedServerId);
        if ($server === null) {
            $this->selectedConnectionId = null;

            return;
        }

        if ($this->selectedConnectionId === null || $server->connections->firstWhere('id', $this->selectedConnectionId) === null) {
            $this->selectedConnectionId = $server->connections->sortBy('database')->first()?->id;
        }
    }
};
?>

<div class="mx-auto max-w-5xl space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-6">
        <div>
            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-pg-blue-base/90 dark:text-pg-blue-light/90">Overview</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">Connections</h1>
            <p class="mt-2 max-w-lg text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">Choose a server and saved profile, then open the workspace to browse schemas and run SQL.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a wire:navigate href="{{ url('/servers/create') }}" class="ui-btn-secondary px-4 py-2.5">
                {{ svg('hugeicons-plus-sign', 'h-4 w-4 shrink-0') }}
                Add server
            </a>
            <a wire:navigate href="{{ url('/connections/create') }}" class="ui-btn-primary px-4 py-2.5">
                {{ svg('hugeicons-plus-sign', 'h-4 w-4 shrink-0') }}
                Add connection
            </a>
            <div class="shrink-0">
                <x-theme-toggle />
            </div>
        </div>
    </div>

    @if ($servers->isEmpty())
        <div class="ui-surface-dashed p-12 text-center">
            <p class="text-sm text-zinc-600 dark:text-zinc-400">No servers yet. Add a server, then create a connection profile.</p>
        </div>
    @else
        <div class="ui-surface p-6 md:p-8">
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="space-y-2">
                    <label class="ui-label">Server</label>
                    <select wire:model.live="selectedServerId" class="ui-select">
                        @foreach ($servers as $server)
                            <option value="{{ $server->id }}">{{ $server->name }} — {{ $server->host }}:{{ $server->port }}</option>
                        @endforeach
                    </select>
                </div>
                @php
                    $activeServer = $servers->firstWhere('id', $selectedServerId);
                    $connections = $activeServer?->connections ?? collect();
                @endphp
                <div class="space-y-2">
                    <label class="ui-label">Connection</label>
                    <select
                        wire:model.live="selectedConnectionId"
                        class="ui-select disabled:opacity-50"
                        @if ($connections->isEmpty()) disabled @endif
                    >
                        @forelse ($connections->sortBy('database') as $conn)
                            <option value="{{ $conn->id }}">{{ $conn->database }} ({{ $conn->username }})</option>
                        @empty
                            <option value="">No connections for this server</option>
                        @endforelse
                    </select>
                </div>
            </div>

            @if ($activeServer)
                <div class="mt-8 border-t border-zinc-200/90 pt-8 dark:border-white/[0.06]">
                    <h2 class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $activeServer->name }}</h2>
                    <p class="mt-0.5 text-sm text-zinc-600 dark:text-zinc-400">{{ $activeServer->host }}:{{ $activeServer->port }}</p>
                    @if ($activeServer->notes)
                        <p class="mt-2 text-sm text-zinc-500">{{ $activeServer->notes }}</p>
                    @endif
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a wire:navigate href="{{ url('/servers/'.$activeServer->id.'/edit') }}" class="inline-flex items-center gap-1.5 text-sm text-pg-blue-base hover:text-pg-blue-dark dark:text-pg-blue-light dark:hover:text-white">
                            {{ svg('hugeicons-pencil-edit-01', 'h-4 w-4 shrink-0') }}
                            Edit server
                        </a>
                        <button type="button" wire:click="deleteServer({{ $activeServer->id }})" wire:confirm="Delete this server and all its connection profiles?" class="inline-flex items-center gap-1.5 text-sm text-red-400 hover:text-red-300">
                            {{ svg('hugeicons-waste', 'h-4 w-4 shrink-0') }}
                            Delete server
                        </button>
                    </div>
                </div>
            @endif

            @php
                $activeConn = $activeServer && $selectedConnectionId
                    ? $connections->firstWhere('id', $selectedConnectionId)
                    : null;
            @endphp

            @if ($activeConn)
                <div class="mt-8 border-t border-zinc-200/90 pt-8 dark:border-white/[0.06]">
                    <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-50">{{ $activeConn->database }}</h3>
                    <p class="mt-0.5 text-xs text-zinc-500">{{ $activeConn->username }} · ssl {{ $activeConn->sslmode }}</p>
                    @if ($activeConn->last_error)
                        <p class="mt-2 text-xs text-pg-orange-light/90">Last error: {{ $activeConn->last_error }}</p>
                    @endif
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a wire:navigate href="{{ url('/explorer/'.$activeConn->id) }}" class="ui-btn-primary px-3 py-2 text-sm">
                            {{ svg('hugeicons-link-forward', 'h-4 w-4 shrink-0') }}
                            Open workspace
                        </a>
                        <a wire:navigate href="{{ url('/connections/'.$activeConn->id.'/edit') }}" class="ui-btn-secondary px-3 py-2 text-sm">
                            {{ svg('hugeicons-pencil-edit-01', 'h-4 w-4 shrink-0') }}
                            Edit connection
                        </a>
                        <button type="button" wire:click="deleteConnection({{ $activeConn->id }})" wire:confirm="Remove this connection profile?" class="inline-flex items-center gap-1.5 text-sm text-red-400 hover:text-red-300">
                            {{ svg('hugeicons-waste', 'h-4 w-4 shrink-0') }}
                            Delete connection
                        </button>
                    </div>
                </div>
            @elseif ($activeServer && $connections->isEmpty())
                <p class="mt-8 border-t border-zinc-200/90 pt-8 text-sm text-zinc-600 dark:border-white/[0.06] dark:text-zinc-500">No connections for this server yet. Create a connection profile to continue.</p>
            @endif
        </div>
    @endif
</div>
