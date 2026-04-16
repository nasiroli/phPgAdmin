<?php

use App\Models\Connection;
use App\Models\Server;
use App\Services\PostgresCatalogService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    /** @var Collection<int, Server> */
    public Collection $servers;

    /** @var list<int> */
    public array $expandedServers = [];

    /** @var list<int> */
    public array $expandedConnections = [];

    /** @var list<string> */
    public array $expandedDbKeys = [];

    /** @var list<string> */
    public array $expandedSchemaKeys = [];

    /**
     * connectionId => list of database names
     *
     * @var array<int, list<string>>
     */
    public array $databasesByConnection = [];

    /**
     * "cid|db" => list of schema names
     *
     * @var array<string, list<string>>
     */
    public array $schemasByDb = [];

    /**
     * "cid|db|schema" => list of table rows
     *
     * @var array<string, list<array{name:string,type:string}>>
     */
    public array $tablesBySchema = [];

    /** @var array<int, ?string> */
    public array $connectionErrors = [];

    public function mount(): void
    {
        $this->loadTree();
    }

    #[On('sidebar-refresh')]
    public function loadTree(): void
    {
        $this->servers = Server::query()->with('connections')->orderBy('name')->get();
    }

    /**
     * Expand the tree so the active workspace database (and schema) are visible.
     */
    #[On('sidebar-expand-workspace')]
    public function expandToWorkspace(int $connectionId, ?string $database = null, ?string $schema = null): void
    {
        $conn = Connection::query()->find($connectionId);
        if ($conn === null) {
            return;
        }

        $serverId = $conn->server_id;
        $catalog = app(PostgresCatalogService::class);

        if (! in_array($serverId, $this->expandedServers, true)) {
            $this->expandedServers[] = $serverId;
        }
        if (! in_array($connectionId, $this->expandedConnections, true)) {
            $this->expandedConnections[] = $connectionId;
        }

        if (! isset($this->databasesByConnection[$connectionId])) {
            [$dbs, $err] = $catalog->listDatabases($conn, $conn->database);
            if ($err !== null) {
                $this->connectionErrors[$connectionId] = $err;
                $this->databasesByConnection[$connectionId] = [];
            } else {
                $this->connectionErrors[$connectionId] = null;
                $this->databasesByConnection[$connectionId] = $dbs;
            }
        }

        $database = $database ?? '';
        if ($database === '') {
            return;
        }

        $dbKey = $this->dbKey($connectionId, $database);
        if (! in_array($dbKey, $this->expandedDbKeys, true)) {
            $this->expandedDbKeys[] = $dbKey;
        }

        if (! isset($this->schemasByDb[$dbKey])) {
            [$schemas, $err] = $catalog->listSchemas($conn, $database);
            $this->schemasByDb[$dbKey] = $err !== null ? [] : $schemas;
            if ($err !== null) {
                $this->connectionErrors[$connectionId] = $err;
            }
        }

        $schema = $schema ?? '';
        if ($schema === '') {
            return;
        }

        $schKey = $this->schemaKey($connectionId, $database, $schema);
        if (! in_array($schKey, $this->expandedSchemaKeys, true)) {
            $this->expandedSchemaKeys[] = $schKey;
        }

        if (! isset($this->tablesBySchema[$schKey])) {
            [$tables, $err] = $catalog->listTables($conn, $database, $schema);
            if ($err !== null) {
                $this->tablesBySchema[$schKey] = [];
                $this->connectionErrors[$connectionId] = $err;
            } else {
                $list = [];
                foreach ($tables as $t) {
                    $list[] = [
                        'name' => $t['name'],
                        'type' => ($t['type'] ?? '') === 'VIEW' ? 'view' : 'table',
                    ];
                }
                $this->tablesBySchema[$schKey] = $list;
            }
        }
    }

    public function toggleServer(int $id): void
    {
        $this->expandedServers = $this->toggleList($this->expandedServers, $id);
    }

    public function toggleConnection(int $id, PostgresCatalogService $catalog): void
    {
        $this->expandedConnections = $this->toggleList($this->expandedConnections, $id);
        if (in_array($id, $this->expandedConnections, true) && ! isset($this->databasesByConnection[$id])) {
            $conn = Connection::query()->find($id);
            if (! $conn) {
                return;
            }
            [$dbs, $err] = $catalog->listDatabases($conn, $conn->database);
            if ($err !== null) {
                $this->connectionErrors[$id] = $err;
                $this->databasesByConnection[$id] = [];
            } else {
                $this->connectionErrors[$id] = null;
                $this->databasesByConnection[$id] = $dbs;
            }
        }
    }

    public function toggleDatabase(int $connectionId, string $database, PostgresCatalogService $catalog): void
    {
        $key = $this->dbKey($connectionId, $database);
        $this->expandedDbKeys = $this->toggleStringList($this->expandedDbKeys, $key);
        if (in_array($key, $this->expandedDbKeys, true) && ! isset($this->schemasByDb[$key])) {
            $conn = Connection::query()->find($connectionId);
            if (! $conn) {
                return;
            }
            [$schemas, $err] = $catalog->listSchemas($conn, $database);
            $this->schemasByDb[$key] = $err !== null ? [] : $schemas;
            if ($err !== null) {
                $this->connectionErrors[$connectionId] = $err;
            }
        }
    }

    public function toggleSchema(int $connectionId, string $database, string $schema, PostgresCatalogService $catalog): void
    {
        $key = $this->schemaKey($connectionId, $database, $schema);
        $this->expandedSchemaKeys = $this->toggleStringList($this->expandedSchemaKeys, $key);
        if (in_array($key, $this->expandedSchemaKeys, true) && ! isset($this->tablesBySchema[$key])) {
            $conn = \App\Models\Connection::query()->find($connectionId);
            if (! $conn) {
                return;
            }
            [$tables, $err] = $catalog->listTables($conn, $database, $schema);
            if ($err !== null) {
                $this->tablesBySchema[$key] = [];
                $this->connectionErrors[$connectionId] = $err;
            } else {
                $list = [];
                foreach ($tables as $t) {
                    $list[] = [
                        'name' => $t['name'],
                        'type' => ($t['type'] ?? '') === 'VIEW' ? 'view' : 'table',
                    ];
                }
                $this->tablesBySchema[$key] = $list;
            }
        }
    }

    public function explorerUrl(int $connectionId, string $database, ?string $schema = null, ?string $table = null): string
    {
        $q = ['db' => $database];
        if ($schema !== null && $schema !== '') {
            $q['schema'] = $schema;
        }
        if ($table !== null && $table !== '') {
            $q['tbl'] = $table;
        }

        return route('explorer', ['connection' => $connectionId], absolute: false).'?'.http_build_query($q);
    }

    /**
     * @param  list<int>  $list
     * @return list<int>
     */
    private function toggleList(array $list, int $id): array
    {
        if (in_array($id, $list, true)) {
            return array_values(array_filter($list, fn ($x) => $x !== $id));
        }
        $list[] = $id;

        return $list;
    }

    /**
     * @param  list<string>  $list
     * @return list<string>
     */
    private function toggleStringList(array $list, string $key): array
    {
        if (in_array($key, $list, true)) {
            return array_values(array_filter($list, fn ($x) => $x !== $key));
        }
        $list[] = $key;

        return $list;
    }

    private function dbKey(int $connectionId, string $database): string
    {
        return $connectionId.'|'.$database;
    }

    private function schemaKey(int $connectionId, string $database, string $schema): string
    {
        return $connectionId.'|'.$database.'|'.$schema;
    }
};
?>

<div class="flex min-h-0 flex-1 flex-col text-sm">
    <a wire:navigate href="{{ url('/') }}" class="mb-4 flex items-center gap-2 rounded-xl px-2 py-2 text-sm font-medium text-emerald-600/95 transition hover:bg-zinc-200/60 dark:text-emerald-400/95 dark:hover:bg-white/[0.04]">
        {{ svg('hugeicons-dashboard-square-01', 'h-4 w-4 shrink-0') }}
        Dashboard
    </a>
    <div class="mb-2 flex items-center gap-2 text-[0.65rem] font-semibold uppercase tracking-[0.15em] text-zinc-500">
        {{ svg('hugeicons-server-stack', 'h-3.5 w-3.5 shrink-0 text-zinc-500 dark:text-zinc-600') }}
        Servers
    </div>
    <nav class="min-h-0 flex-1 space-y-0.5 overflow-y-auto pr-1">
        @foreach ($servers as $server)
            <div>
                <button
                    type="button"
                    wire:click="toggleServer({{ $server->id }})"
                    class="flex w-full items-center gap-1 rounded-lg px-2 py-1.5 text-left text-zinc-800 transition hover:bg-zinc-200/60 dark:text-zinc-200 dark:hover:bg-white/[0.04]"
                >
                    {{ svg(in_array($server->id, $expandedServers) ? 'hugeicons-arrow-down-01' : 'hugeicons-arrow-right-01', 'h-3.5 w-3.5 shrink-0 text-zinc-500') }}
                    {{ svg('hugeicons-server-stack', 'h-3.5 w-3.5 shrink-0 text-zinc-500') }}
                    <span class="truncate font-medium">{{ $server->name }}</span>
                </button>
                @if (in_array($server->id, $expandedServers))
                    @foreach ($server->connections as $conn)
                        <div class="ml-2 border-l border-zinc-200/90 pl-2 dark:border-white/[0.06]">
                            <button
                                type="button"
                                wire:click="toggleConnection({{ $conn->id }})"
                                class="flex w-full items-center gap-1 rounded-lg px-2 py-1 text-left text-zinc-700 transition hover:bg-zinc-200/60 dark:text-zinc-300 dark:hover:bg-white/[0.04]"
                            >
                                {{ svg(in_array($conn->id, $expandedConnections) ? 'hugeicons-arrow-down-01' : 'hugeicons-arrow-right-01', 'h-3.5 w-3.5 shrink-0 text-zinc-500') }}
                                {{ svg('hugeicons-database', 'h-3.5 w-3.5 shrink-0 text-zinc-500') }}
                                <span class="truncate">{{ $conn->database }}</span>
                            </button>
                            @if (isset($connectionErrors[$conn->id]) && $connectionErrors[$conn->id])
                                <p class="ml-4 text-xs text-amber-400/90">{{ \Illuminate\Support\Str::limit($connectionErrors[$conn->id], 80) }}</p>
                            @endif
                            @if (in_array($conn->id, $expandedConnections))
                                @foreach ($databasesByConnection[$conn->id] ?? [] as $db)
                                    @php $dbKey = $conn->id.'|'.$db; @endphp
                                    <div class="ml-2 border-l border-zinc-200/80 pl-2 dark:border-white/[0.05]">
                                        <button
                                            type="button"
                                            wire:click="toggleDatabase({{ $conn->id }}, @js($db))"
                                            class="flex w-full items-center gap-1 rounded-md px-2 py-0.5 text-left text-zinc-600 transition hover:bg-zinc-200/60 dark:text-zinc-400 dark:hover:bg-white/[0.04]"
                                        >
                                            {{ svg(in_array($dbKey, $expandedDbKeys) ? 'hugeicons-arrow-down-01' : 'hugeicons-arrow-right-01', 'h-3 w-3 shrink-0 text-zinc-600') }}
                                            {{ svg('hugeicons-database-01', 'h-3 w-3 shrink-0 text-zinc-600') }}
                                            <span class="truncate font-mono text-xs">{{ $db }}</span>
                                        </button>
                                        @if (in_array($dbKey, $expandedDbKeys))
                                            <a
                                                wire:navigate
                                                href="{{ $this->explorerUrl($conn->id, $db) }}"
                                                class="ml-4 flex items-center gap-1 truncate rounded-md px-2 py-0.5 text-xs text-emerald-600/95 transition hover:bg-zinc-200/60 dark:text-emerald-400/95 dark:hover:bg-white/[0.04]"
                                            >
                                                {{ svg('hugeicons-link-forward', 'h-3 w-3 shrink-0') }}
                                                Open workspace
                                            </a>
                                            @foreach ($schemasByDb[$dbKey] ?? [] as $sch)
                                                @php $schKey = $conn->id.'|'.$db.'|'.$sch; @endphp
                                                <div class="ml-2 border-l border-zinc-200/80 pl-2 dark:border-white/[0.05]">
                                                    <button
                                                        type="button"
                                                        wire:click="toggleSchema({{ $conn->id }}, @js($db), @js($sch))"
                                                        class="flex w-full items-center gap-1 rounded-md px-2 py-0.5 text-left text-zinc-600 transition hover:bg-zinc-200/60 dark:text-zinc-400 dark:hover:bg-white/[0.04]"
                                                    >
                                                        {{ svg(in_array($schKey, $expandedSchemaKeys) ? 'hugeicons-arrow-down-01' : 'hugeicons-arrow-right-01', 'h-3 w-3 shrink-0 text-zinc-600') }}
                                                        {{ svg('hugeicons-structure-01', 'h-3 w-3 shrink-0 text-zinc-600') }}
                                                        <span class="truncate text-xs">{{ $sch }}</span>
                                                    </button>
                                                    @if (in_array($schKey, $expandedSchemaKeys))
                                                        @foreach ($tablesBySchema[$schKey] ?? [] as $tb)
                                                            <a
                                                                wire:navigate
                                                                href="{{ $this->explorerUrl($conn->id, $db, $sch, $tb['name']) }}"
                                                                class="ml-4 flex items-center gap-1 truncate rounded-md px-2 py-0.5 text-xs text-zinc-700 transition hover:bg-zinc-200/60 dark:text-zinc-300 dark:hover:bg-white/[0.04]"
                                                            >
                                                                {{ svg($tb['type'] === 'view' ? 'hugeicons-view' : 'hugeicons-table-01', 'h-3 w-3 shrink-0 text-zinc-600') }}
                                                                {{ $tb['name'] }}
                                                            </a>
                                                        @endforeach
                                                    @endif
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        @endforeach
    </nav>
</div>
