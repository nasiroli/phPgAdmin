<?php

use App\Models\Connection as PgConnection;
use App\Services\PostgresCatalogService;
use App\Services\PostgresConnectionService;
use App\Services\PostgresTableAdminService;
use App\Services\SqlSafetyChecker;
use App\Support\CellValueFormatter;
use App\Support\PostgresIdentifier;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Workspace')] class extends Component
{
    #[Locked]
    public PgConnection $connection;

    #[Url]
    public string $db = '';

    #[Url]
    public string $schema = '';

    #[Url]
    public string $tbl = '';

    public string $activeTab = 'browse';

    public bool $allowWrites = false;

    /** @var list<string> */
    public array $databases = [];

    /** @var list<string> */
    public array $schemas = [];

    public string $sql = 'select 1 as ok';

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public int $browsePage = 1;

    public int $perPage = 50;

    public int $totalRows = 0;

    /** @var list<string>|null */
    public ?array $browseColumns = null;

    /** @var list<array<string, mixed>>|null */
    public ?array $browseRows = null;

    /** @var list<object>|null */
    public ?array $structureColumns = null;

    /** @var list<object>|null */
    public ?array $indexRows = null;

    /** @var list<string> */
    public array $pkColumns = [];

    /** @var list<int> */
    public array $selectedRowIndexes = [];

    public string $newColName = '';

    public string $newColType = 'text';

    public bool $newColNullable = true;

    public string $newColDefault = '';

    public string $newIndexName = '';

    public string $newIndexCols = '';

    public string $newTableName = '';

    /** @var list<array{name:string,type:string,nullable:bool}> */
    public array $newTableCols = [['name' => 'id', 'type' => 'serial', 'nullable' => false]];

    /** @var array<string, string|null> */
    public array $insertValues = [];

    /** @var array<string, string|null> */
    public array $editValues = [];

    public bool $showEditModal = false;

    /** @var array<string, string|null> */
    public array $editWherePk = [];

    public function mount(PgConnection $connection): void
    {
        $this->connection = $connection->load('server');
        if ($this->db === '') {
            $this->db = $connection->database;
        }
        $this->reloadMeta(app(PostgresCatalogService::class));
        $this->loadCurrentObject();
        $this->syncSidebarExplorerTree();
    }

    public function updatedDb(): void
    {
        $this->schema = '';
        $this->tbl = '';
        $this->reloadMeta(app(PostgresCatalogService::class));
        $this->loadCurrentObject();
        $this->syncSidebarExplorerTree();
    }

    public function updatedSchema(): void
    {
        $this->tbl = '';
        $this->loadCurrentObject();
        $this->syncSidebarExplorerTree();
    }

    public function updatedTbl(): void
    {
        $this->loadCurrentObject();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->loadCurrentObject();
    }

    public function reloadMeta(PostgresCatalogService $cat): void
    {
        $this->errorMessage = null;
        [$dbs, $e1] = $cat->listDatabases($this->connection, $this->db);
        if ($e1 !== null) {
            $this->errorMessage = $e1;
            $this->databases = [];

            return;
        }
        $this->databases = $dbs;
        [$sch, $e2] = $cat->listSchemas($this->connection, $this->db);
        if ($e2 !== null) {
            $this->errorMessage = $e2;
            $this->schemas = [];

            return;
        }
        $this->schemas = $sch;
    }

    public function loadCurrentObject(PostgresCatalogService $cat = null, PostgresTableAdminService $admin = null): void
    {
        $cat ??= app(PostgresCatalogService::class);
        $this->browseColumns = null;
        $this->browseRows = null;
        $this->structureColumns = null;
        $this->indexRows = null;
        $this->pkColumns = [];
        $this->selectedRowIndexes = [];
        if ($this->tbl === '' || $this->schema === '') {
            return;
        }
        try {
            PostgresIdentifier::assertValid($this->schema, 'Schema');
            PostgresIdentifier::assertValid($this->tbl, 'Table');
        } catch (\InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        [$pks, $epk] = $cat->primaryKeyColumns($this->connection, $this->db, $this->schema, $this->tbl);
        $this->pkColumns = ($epk === null && is_array($pks)) ? $pks : [];

        if ($this->activeTab === 'browse') {
            $this->loadBrowsePage($cat);
        }
        if (in_array($this->activeTab, ['structure', 'insert'], true)) {
            [$cols, $ec] = $cat->listColumns($this->connection, $this->db, $this->schema, $this->tbl);
            $this->structureColumns = $ec === null ? $cols : null;
            if ($ec !== null) {
                $this->errorMessage = $ec;
            }
        }
        if ($this->activeTab === 'indexes') {
            [$idx, $ei] = $cat->listIndexes($this->connection, $this->db, $this->schema, $this->tbl);
            $this->indexRows = $ei === null ? $idx : null;
            if ($ei !== null) {
                $this->errorMessage = $ei;
            }
        }
        if ($this->activeTab === 'insert' && $this->structureColumns) {
            foreach ($this->structureColumns as $c) {
                if (! isset($this->insertValues[$c->column_name])) {
                    $this->insertValues[$c->column_name] = '';
                }
            }
        }
    }

    public function gotoPage(int $page): void
    {
        $this->browsePage = max(1, $page);
        $this->loadBrowsePage(app(PostgresCatalogService::class));
    }

    public function loadBrowsePage(PostgresCatalogService $cat): void
    {
        if ($this->tbl === '' || $this->schema === '') {
            return;
        }
        [$total, $et] = $cat->countRows($this->connection, $this->db, $this->schema, $this->tbl);
        if ($et !== null) {
            $this->errorMessage = $et;

            return;
        }
        $this->totalRows = $total;
        $offset = ($this->browsePage - 1) * $this->perPage;
        [$rows, $er] = $cat->fetchPage($this->connection, $this->db, $this->schema, $this->tbl, $this->perPage, $offset);
        if ($er !== null) {
            $this->errorMessage = $er;

            return;
        }
        if ($rows === []) {
            $this->browseColumns = [];
            $this->browseRows = [];

            return;
        }
        $first = (array) $rows[0];
        $this->browseColumns = array_keys($first);
        $this->browseRows = array_map(fn ($r) => (array) $r, $rows);
    }

    public function openEdit(int $index): void
    {
        if ($this->browseRows === null || ! isset($this->browseRows[$index])) {
            return;
        }
        $row = $this->browseRows[$index];
        $this->editValues = $row;
        $this->editWherePk = [];
        foreach ($this->pkColumns as $pk) {
            $this->editWherePk[$pk] = isset($row[$pk]) ? (is_scalar($row[$pk]) ? (string) $row[$pk] : json_encode($row[$pk])) : null;
        }
        $this->showEditModal = true;
    }

    public function saveEdit(PostgresTableAdminService $admin): void
    {
        if (! $this->allowWrites) {
            $this->errorMessage = 'Enable writes to save changes.';

            return;
        }
        $data = [];
        foreach ($this->editValues as $k => $v) {
            if (in_array($k, $this->pkColumns, true)) {
                continue;
            }
            $data[$k] = $v;
        }
        $r = $admin->updateRow($this->connection, $this->db, $this->schema, $this->tbl, $data, $this->editWherePk);
        if ($r['ok']) {
            $this->showEditModal = false;
            $this->statusMessage = 'Row updated.';
            $this->loadBrowsePage(app(PostgresCatalogService::class));
        } else {
            $this->errorMessage = $r['error'];
        }
    }

    public function deleteSelected(PostgresTableAdminService $admin): void
    {
        if (! $this->allowWrites) {
            $this->errorMessage = 'Enable writes to delete rows.';

            return;
        }
        if ($this->browseRows === null || $this->pkColumns === []) {
            $this->errorMessage = 'Cannot delete without primary key.';

            return;
        }
        $maps = [];
        foreach ($this->selectedRowIndexes as $si) {
            $i = (int) $si;
            if (! isset($this->browseRows[$i])) {
                continue;
            }
            $row = $this->browseRows[$i];
            $w = [];
            foreach ($this->pkColumns as $pk) {
                $w[$pk] = isset($row[$pk]) ? (is_scalar($row[$pk]) ? (string) $row[$pk] : json_encode($row[$pk])) : null;
            }
            $maps[] = $w;
        }
        if ($maps === []) {
            return;
        }
        $r = $admin->deleteRows($this->connection, $this->db, $this->schema, $this->tbl, $maps);
        if ($r['ok']) {
            $this->statusMessage = 'Deleted '.count($maps).' row(s).';
            $this->selectedRowIndexes = [];
            $this->loadBrowsePage(app(PostgresCatalogService::class));
        } else {
            $this->errorMessage = $r['error'];
        }
    }

    public function runInsert(PostgresTableAdminService $admin): void
    {
        if (! $this->allowWrites) {
            $this->errorMessage = 'Enable writes to insert.';

            return;
        }
        $r = $admin->insertRow($this->connection, $this->db, $this->schema, $this->tbl, $this->insertValues);
        if ($r['ok']) {
            $this->statusMessage = 'Row inserted.';
            $this->insertValues = [];
            $this->loadBrowsePage(app(PostgresCatalogService::class));
        } else {
            $this->errorMessage = $r['error'];
        }
    }

    public function addColumn(PostgresTableAdminService $admin): void
    {
        if (! $this->allowWrites) {
            $this->errorMessage = 'Enable writes for DDL.';

            return;
        }
        $r = $admin->addColumn(
            $this->connection,
            $this->db,
            $this->schema,
            $this->tbl,
            $this->newColName,
            $this->newColType,
            $this->newColNullable,
            $this->newColDefault !== '' ? $this->newColDefault : null
        );
        if ($r['ok']) {
            $this->statusMessage = 'Column added.';
            $this->newColName = '';
            $this->newColType = 'text';
            $this->newColDefault = '';
            $this->loadCurrentObject();
        } else {
            $this->errorMessage = $r['error'];
        }
    }

    public function dropColumn(string $name, PostgresTableAdminService $admin): void
    {
        if (! $this->allowWrites) {
            return;
        }
        $r = $admin->dropColumn($this->connection, $this->db, $this->schema, $this->tbl, $name);
        if ($r['ok']) {
            $this->statusMessage = 'Column dropped.';
            $this->loadCurrentObject();
        } else {
            $this->errorMessage = $r['error'];
        }
    }

    public function createIndex(PostgresTableAdminService $admin): void
    {
        if (! $this->allowWrites) {
            return;
        }
        $r = $admin->createIndex($this->connection, $this->db, $this->schema, $this->tbl, $this->newIndexName, $this->newIndexCols);
        if ($r['ok']) {
            $this->statusMessage = 'Index created.';
            $this->newIndexName = '';
            $this->newIndexCols = '';
            $this->loadCurrentObject();
        } else {
            $this->errorMessage = $r['error'];
        }
    }

    public function dropIndex(string $name, PostgresTableAdminService $admin): void
    {
        if (! $this->allowWrites) {
            return;
        }
        $r = $admin->dropIndex($this->connection, $this->db, $this->schema, $name);
        if ($r['ok']) {
            $this->statusMessage = 'Index dropped.';
            $this->loadCurrentObject();
        } else {
            $this->errorMessage = $r['error'];
        }
    }

    public function createTable(PostgresTableAdminService $admin): void
    {
        if (! $this->allowWrites || $this->schema === '') {
            return;
        }
        PostgresIdentifier::assertValid($this->newTableName, 'Table');
        $cols = [];
        foreach ($this->newTableCols as $c) {
            if ($c['name'] === '' || $c['type'] === '') {
                continue;
            }
            $cols[] = [
                'name' => $c['name'],
                'type' => $c['type'],
                'nullable' => $c['nullable'],
                'default' => null,
            ];
        }
        $r = $admin->createTable($this->connection, $this->db, $this->schema, $this->newTableName, $cols);
        if ($r['ok']) {
            $this->statusMessage = 'Table created.';
            $this->tbl = $this->newTableName;
            $this->newTableName = '';
            $this->activeTab = 'browse';
            $this->loadCurrentObject();
        } else {
            $this->errorMessage = $r['error'];
        }
    }

    public function dropTable(PostgresTableAdminService $admin): void
    {
        if (! $this->allowWrites || $this->tbl === '') {
            return;
        }
        $r = $admin->dropTable($this->connection, $this->db, $this->schema, $this->tbl);
        if ($r['ok']) {
            $this->statusMessage = 'Table dropped.';
            $this->tbl = '';
            $this->loadCurrentObject();
        } else {
            $this->errorMessage = $r['error'];
        }
    }

    public function truncateTable(PostgresTableAdminService $admin): void
    {
        if (! $this->allowWrites || $this->tbl === '') {
            return;
        }
        $r = $admin->truncateTable($this->connection, $this->db, $this->schema, $this->tbl);
        if ($r['ok']) {
            $this->statusMessage = 'Table truncated.';
            $this->loadBrowsePage(app(PostgresCatalogService::class));
        } else {
            $this->errorMessage = $r['error'];
        }
    }

    public function addNewTableColumnRow(): void
    {
        $this->newTableCols[] = ['name' => '', 'type' => 'text', 'nullable' => true];
    }

    public function runSql(PostgresConnectionService $pg, SqlSafetyChecker $guard): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
        $sql = trim($this->sql);
        if ($sql === '') {
            $this->errorMessage = 'Enter SQL.';

            return;
        }
        $readOnly = $guard->isReadOnlyStatement($sql);
        if (! $readOnly && ! $this->allowWrites) {
            $this->errorMessage = 'Enable writes for this statement.';

            return;
        }
        [$result, $err] = $pg->withConnection($this->connection, function ($conn) use ($readOnly, $sql) {
            if ($readOnly) {
                $rows = $conn->select($sql);
                if (count($rows) > 500) {
                    return ['truncate' => true, 'rows' => array_slice($rows, 0, 500)];
                }

                return ['truncate' => false, 'rows' => $rows];
            }

            $affected = $conn->affectingStatement($sql);

            return ['affected' => $affected];
        }, $this->db);
        if ($err !== null) {
            $this->errorMessage = $err;

            return;
        }
        if (isset($result['affected'])) {
            $this->statusMessage = 'Affected: '.(string) $result['affected'];
            $this->reloadMeta(app(PostgresCatalogService::class));
            $this->loadCurrentObject();

            return;
        }
        $rows = $result['rows'] ?? [];
        $this->browseColumns = $rows === [] ? [] : array_keys((array) $rows[0]);
        $this->browseRows = array_map(fn ($r) => (array) $r, $rows);
        if (($result['truncate'] ?? false) === true) {
            $this->statusMessage = 'Limited to 500 rows.';
        }
    }

    public function testConnection(PostgresConnectionService $pg): void
    {
        $r = $pg->testConnection($this->connection);
        if ($r['ok']) {
            $this->statusMessage = 'Connection OK.';
            $this->connection->refresh();
        } else {
            $this->errorMessage = $r['error'] ?? 'Failed.';
        }
    }

    /**
     * Keep the left object browser expanded to match the workspace database/schema.
     */
    public function syncSidebarExplorerTree(): void
    {
        $this->dispatch(
            'sidebar-expand-workspace',
            connectionId: $this->connection->id,
            database: $this->db !== '' ? $this->db : null,
            schema: $this->schema !== '' ? $this->schema : null,
        );
    }
};
?>

@php
    $c = $connection;
@endphp

<div class="space-y-8">
    <div class="flex flex-wrap items-start justify-between gap-6">
        <div>
            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-pg-blue-base/90 dark:text-pg-blue-light/90">Workspace</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">Query &amp; browse</h1>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $c->server->host }} · {{ $c->username }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="testConnection" class="ui-btn-secondary px-3 py-2 text-sm">
                {{ svg('hugeicons-test-tube', 'h-4 w-4 shrink-0') }}
                Test connection
            </button>
            <a wire:navigate href="{{ url('/') }}" class="ui-btn-ghost px-3 py-2 text-sm">
                {{ svg('hugeicons-dashboard-square-01', 'h-4 w-4 shrink-0') }}
                Dashboard
            </a>
            <div class="shrink-0">
                <x-theme-toggle />
            </div>
        </div>
    </div>

    @if ($statusMessage)
        <div class="rounded-xl border border-pg-blue-light/25 bg-pg-blue-dark/10 px-4 py-3 text-sm text-pg-blue-dark dark:border-pg-blue-light/30 dark:bg-pg-blue-base/20 dark:text-pg-blue-light/95">{{ $statusMessage }}</div>
    @endif
    @if ($errorMessage)
        <div class="rounded-xl border border-red-500/20 bg-red-950/35 px-4 py-3 text-sm text-red-200/95">{{ $errorMessage }}</div>
    @endif

    <div class="ui-surface flex flex-wrap gap-4 p-5 md:gap-6 md:p-6">
        <div class="space-y-2">
            <label class="ui-label">Database</label>
            <select wire:model.live="db" class="ui-select">
                @foreach ($databases as $d)
                    <option value="{{ $d }}">{{ $d }}</option>
                @endforeach
            </select>
        </div>
        <div class="space-y-2">
            <label class="ui-label">Schema</label>
            <select wire:model.live="schema" class="ui-select">
                <option value="">—</option>
                @foreach ($schemas as $s)
                    <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="space-y-2 flex flex-col">
            <label class="ui-label">Table</label>
            <input type="text" wire:model.live.debounce.400ms="tbl" placeholder="table_name" class="ui-field w-40 font-mono" />
        </div>

        <div class="flex items-end pb-0.5">
            <label class="inline-flex cursor-pointer items-center gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                <input type="checkbox" wire:model.live="allowWrites" class="rounded border-zinc-300 bg-white text-pg-blue-base accent-pg-blue-base focus:ring-pg-blue-light/30 dark:border-white/15 dark:bg-zinc-950 dark:text-pg-blue-light" />
                Allow writes
            </label>
        </div>
    </div>

    @if ($schema !== '' && $tbl !== '')
        <div class="flex flex-wrap gap-1 rounded-xl bg-zinc-100 dark:bg-zinc-950/50 p-1 ring-1 ring-zinc-200/80 dark:ring-white/[0.06]">
            @foreach ([
                'browse' => ['label' => 'Browse', 'icon' => 'hugeicons-table-01'],
                'structure' => ['label' => 'Structure', 'icon' => 'hugeicons-structure-01'],
                'insert' => ['label' => 'Insert', 'icon' => 'hugeicons-insert-row'],
                'indexes' => ['label' => 'Indexes', 'icon' => 'hugeicons-layers-01'],
                'sql' => ['label' => 'SQL', 'icon' => 'hugeicons-sql'],
                'ops' => ['label' => 'Operations', 'icon' => 'hugeicons-settings'],
            ] as $key => $tab)
                <button type="button" wire:click="setTab('{{ $key }}')" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm transition {{ $activeTab === $key ? 'bg-zinc-200/90 font-medium text-zinc-900 shadow-sm dark:bg-white/[0.08] dark:text-zinc-50' : 'text-zinc-600 hover:bg-zinc-200/60 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/[0.04] dark:hover:text-zinc-200' }}">
                    {{ svg($tab['icon'], 'h-4 w-4 shrink-0 opacity-90') }}
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </div>

        @if ($activeTab === 'browse' && $browseColumns !== null)
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm text-zinc-600 dark:text-zinc-400">Rows: {{ $totalRows }}</span>
                <button type="button" wire:click="gotoPage({{ $browsePage - 1 }})" @if($browsePage <= 1) disabled @endif class="inline-flex items-center gap-1 rounded border border-zinc-200 px-2 py-1 text-sm text-zinc-800 disabled:opacity-40 dark:border-white/10 dark:text-zinc-200">
                    {{ svg('hugeicons-arrow-left-01', 'h-4 w-4 shrink-0') }}
                    Prev
                </button>
                <button type="button" wire:click="gotoPage({{ $browsePage + 1 }})" class="inline-flex items-center gap-1 rounded border border-zinc-200 px-2 py-1 text-sm text-zinc-800 dark:border-white/10 dark:text-zinc-200">
                    Next
                    {{ svg('hugeicons-arrow-right-01', 'h-4 w-4 shrink-0') }}
                </button>
                @if (count($pkColumns) > 0)
                    <button type="button" wire:click="deleteSelected" wire:confirm="Delete selected rows?" class="ml-auto inline-flex items-center gap-1.5 rounded bg-red-900/60 px-3 py-1 text-sm text-red-100">
                        {{ svg('hugeicons-waste', 'h-4 w-4 shrink-0') }}
                        Delete selected
                    </button>
                @endif
            </div>
            <div class="overflow-x-auto rounded-lg border border-zinc-200/80 dark:border-white/[0.06]">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-100/90 dark:bg-zinc-900/80">
                        <tr>
                            @if (count($pkColumns) > 0)
                                <th class="w-10 px-2"></th>
                            @endif
                            @foreach ($browseColumns as $col)
                                <th class="px-3 py-2 text-left text-zinc-700 dark:text-zinc-300">{{ $col }}</th>
                            @endforeach
                            <th class="px-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($browseRows ?? [] as $idx => $row)
                            <tr wire:key="row-{{ $idx }}" class="border-t border-zinc-200/80 dark:border-white/[0.06]">
                                @if (count($pkColumns) > 0)
                                    <td class="px-2">
                                        <input type="checkbox" wire:model="selectedRowIndexes" value="{{ $idx }}" class="rounded border-zinc-300 dark:border-white/15" />
                                    </td>
                                @endif
                                @foreach ($browseColumns as $col)
                                    @php($cell = CellValueFormatter::format($row[$col] ?? null))
                                    <td class="max-w-lg min-w-[8rem] align-top px-3 py-2 font-mono text-xs">
                                        @if ($cell['kind'] === 'json')
                                            <pre class="max-h-48 overflow-auto whitespace-pre-wrap break-words rounded border border-zinc-200/80 dark:border-white/[0.06] bg-zinc-50 dark:bg-zinc-950/80 p-2 text-[11px] leading-snug"><code class="language-json text-zinc-900 dark:text-zinc-100" data-table-hl>{{ $cell['plain'] }}</code></pre>
                                        @else
                                            <span class="block max-w-full truncate" title="{{ $cell['plain'] }}">{{ $cell['plain'] }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-2">
                                    @if (count($pkColumns) > 0)
                                        <button type="button" wire:click="openEdit({{ $idx }})" class="inline-flex items-center gap-1 text-pg-blue-base hover:underline dark:text-pg-blue-light">
                                            {{ svg('hugeicons-pencil-edit-01', 'h-4 w-4 shrink-0') }}
                                            Edit
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($activeTab === 'structure' && $structureColumns !== null)
            <div class="overflow-x-auto rounded-lg border border-zinc-200/80 dark:border-white/[0.06]">
                <table class="min-w-full text-sm">
                    <thead><tr class="bg-zinc-100/90 dark:bg-zinc-900/80"><th class="px-3 py-2 text-left">Column</th><th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2">Null</th><th class="px-3 py-2 text-left">Default</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($structureColumns as $col)
                            <tr class="border-t border-zinc-200/80 dark:border-white/[0.06]">
                                <td class="px-3 py-2 font-mono text-xs">{{ $col->column_name }}</td>
                                <td class="px-3 py-2">{{ $col->udt_name }}</td>
                                <td class="px-3 py-2 text-center">{{ $col->is_nullable }}</td>
                                <td class="px-3 py-2 text-xs text-zinc-600 dark:text-zinc-400">{{ $col->column_default }}</td>
                                <td class="px-2">
                                    <button type="button" wire:click="dropColumn(@js($col->column_name))" wire:confirm="Drop column?" class="inline-flex items-center gap-1 text-red-400 hover:underline">
                                        {{ svg('hugeicons-waste', 'h-4 w-4 shrink-0') }}
                                        Drop
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4 grid gap-2 rounded-lg border border-zinc-200/80 dark:border-white/[0.06] p-4 md:grid-cols-4">
                <input wire:model="newColName" placeholder="name" class="rounded border border-zinc-200 dark:border-white/10 bg-white px-2 py-2 text-sm text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100" />
                <input wire:model="newColType" placeholder="type e.g. text" class="rounded border border-zinc-200 dark:border-white/10 bg-white px-2 py-2 text-sm text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100" />
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="newColNullable" /> nullable</label>
                <input wire:model="newColDefault" placeholder="default (optional)" class="rounded border border-zinc-200 dark:border-white/10 bg-white px-2 py-2 text-sm text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100" />
                <button type="button" wire:click="addColumn" class="inline-flex items-center justify-center gap-2 rounded bg-pg-blue-dark px-3 py-2 text-sm hover:bg-pg-blue-base md:col-span-4">
                    {{ svg('hugeicons-plus-sign', 'h-4 w-4 shrink-0') }}
                    Add column
                </button>
            </div>
        @endif

        @if ($activeTab === 'insert' && $structureColumns !== null)
            <div class="grid max-w-xl gap-3">
                @foreach ($structureColumns as $col)
                    <div>
                        <label class="text-xs text-zinc-500">{{ $col->column_name }} ({{ $col->udt_name }})</label>
                        <input type="text" wire:model="insertValues.{{ $col->column_name }}" class="mt-1 w-full rounded border border-zinc-200 dark:border-white/10 bg-white px-3 py-2 text-sm text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100" />
                    </div>
                @endforeach
                <button type="button" wire:click="runInsert" class="inline-flex items-center gap-2 rounded bg-pg-blue-base px-4 py-2 text-sm hover:bg-pg-blue-light">
                    {{ svg('hugeicons-insert-row', 'h-4 w-4 shrink-0') }}
                    Insert
                </button>
            </div>
        @endif

        @if ($activeTab === 'indexes' && $indexRows !== null)
            <ul class="space-y-2">
                @foreach ($indexRows as $ix)
                    <li class="flex flex-wrap items-start justify-between gap-2 rounded border border-zinc-200/80 dark:border-white/[0.06] p-3 text-sm">
                        <div>
                            <div class="font-mono text-pg-blue-base dark:text-pg-blue-light">{{ $ix->indexname }}</div>
                            <pre class="mt-1 max-w-full overflow-x-auto rounded border border-zinc-200/80 dark:border-white/[0.06] bg-zinc-50 dark:bg-zinc-950/80 p-2 text-xs"><code class="language-sql text-zinc-800 dark:text-zinc-200" data-table-hl>{{ $ix->indexdef }}</code></pre>
                        </div>

                        <button type="button" wire:click="dropIndex(@js($ix->indexname))" wire:confirm="Drop index?" class="inline-flex shrink-0 items-center gap-1 text-red-400">
                            {{ svg('hugeicons-waste', 'h-4 w-4 shrink-0') }}
                            Drop
                        </button>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4 flex flex-wrap gap-2">
                <input wire:model="newIndexName" placeholder="index name" class="rounded border border-zinc-200 dark:border-white/10 bg-white px-3 py-2 text-sm text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100" />
                <input wire:model="newIndexCols" placeholder="col1, col2" class="rounded border border-zinc-200 dark:border-white/10 bg-white px-3 py-2 text-sm text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100" />
                <button type="button" wire:click="createIndex" class="inline-flex items-center gap-2 rounded bg-pg-blue-dark px-3 py-2 text-sm hover:bg-pg-blue-base">
                    {{ svg('hugeicons-plus-sign', 'h-4 w-4 shrink-0') }}
                    Create index
                </button>
            </div>
        @endif

        @if ($activeTab === 'sql')
            <textarea id="workspace-sql-textarea" wire:model.debounce.400ms="sql" class="sr-only" rows="1" cols="1" tabindex="-1" aria-hidden="true"></textarea>
            <div wire:ignore data-cm-sql-host class="min-h-[220px] overflow-hidden rounded-lg border border-zinc-200 dark:border-white/10"></div>
            <button type="button" wire:click="runSql" class="mt-2 inline-flex items-center gap-2 rounded bg-pg-blue-base px-4 py-2 text-sm hover:bg-pg-blue-light">
                {{ svg('hugeicons-play', 'h-4 w-4 shrink-0') }}
                Run
            </button>
            @if ($browseColumns !== null && count($browseColumns) > 0)
                <div class="mt-4 overflow-x-auto rounded-lg border border-zinc-200/80 dark:border-white/[0.06]">
                    <table class="min-w-full text-xs">
                        <thead><tr>@foreach ($browseColumns as $col)<th class="px-2 py-1">{{ $col }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($browseRows ?? [] as $row)
                                <tr>
                                    @foreach ($browseColumns as $col)
                                        @php($cell = CellValueFormatter::format($row[$col] ?? null))
                                        <td class="max-w-md align-top border-t border-zinc-200/80 dark:border-white/[0.06] px-2 py-1 font-mono text-[11px]">
                                            @if ($cell['kind'] === 'json')
                                                <pre class="max-h-40 overflow-auto whitespace-pre-wrap break-words rounded border border-zinc-200/80 dark:border-white/[0.06] bg-zinc-50 dark:bg-zinc-950/80 p-1.5"><code class="language-json" data-table-hl>{{ $cell['plain'] }}</code></pre>
                                            @else
                                                {{ $cell['plain'] }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        @if ($activeTab === 'ops')
            <div class="space-y-6">
                <div class="rounded-lg border border-zinc-200/80 dark:border-white/[0.06] p-4">
                    <h3 class="font-medium text-zinc-900 dark:text-white">New table</h3>
                    <input wire:model="newTableName" placeholder="table name" class="mt-2 rounded border border-zinc-200 dark:border-white/10 bg-white px-3 py-2 text-sm text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100" />
                    @foreach ($newTableCols as $i => $row)
                        <div class="mt-2 flex flex-wrap gap-2">
                            <input wire:model="newTableCols.{{ $i }}.name" placeholder="col" class="rounded border border-zinc-200 dark:border-white/10 bg-white px-2 py-1 text-sm text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100" />
                            <input wire:model="newTableCols.{{ $i }}.type" placeholder="type" class="rounded border border-zinc-200 dark:border-white/10 bg-white px-2 py-1 text-sm text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100" />
                            <label class="text-sm"><input type="checkbox" wire:model="newTableCols.{{ $i }}.nullable" /> null</label>
                        </div>
                    @endforeach
                    <button type="button" wire:click="addNewTableColumnRow" class="mt-2 inline-flex items-center gap-1 text-sm text-pg-blue-base dark:text-pg-blue-light">
                        {{ svg('hugeicons-plus-sign', 'h-4 w-4 shrink-0') }}
                        Add column
                    </button>
                    <button type="button" wire:click="createTable" class="mt-3 inline-flex items-center gap-2 rounded bg-pg-blue-base px-4 py-2 text-sm hover:bg-pg-blue-light">
                        {{ svg('hugeicons-plus-sign', 'h-4 w-4 shrink-0') }}
                        Create table
                    </button>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="button" wire:click="truncateTable" wire:confirm="Truncate this table?" class="inline-flex items-center gap-2 rounded border border-pg-orange-dark bg-pg-orange-dark/10 px-4 py-2 text-sm text-pg-orange-light">
                        {{ svg('hugeicons-eraser', 'h-4 w-4 shrink-0') }}
                        Truncate
                    </button>
                    <button type="button" wire:click="dropTable" wire:confirm="DROP TABLE permanently?" class="inline-flex items-center gap-2 rounded bg-red-900/50 px-4 py-2 text-sm text-red-200">
                        {{ svg('hugeicons-waste', 'h-4 w-4 shrink-0') }}
                        Drop table
                    </button>
                </div>
            </div>
        @endif
    @else
        <p class="text-zinc-600 dark:text-zinc-500">Pick schema and table above, or use the sidebar. SQL tab works without a selected table.</p>
    @endif

    @if ($showEditModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" wire:click.self="$set('showEditModal', false)">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-zinc-200 dark:border-white/10 bg-white p-6 shadow-xl dark:bg-zinc-900">
                <h3 class="text-lg font-medium text-zinc-900 dark:text-white">Edit row</h3>
                <div class="mt-4 space-y-3">
                    @foreach ($editValues as $k => $v)
                        <div>
                            <label class="text-xs text-zinc-500">{{ $k }} @if(in_array($k, $pkColumns)) (PK) @endif</label>
                            @if (in_array($k, $pkColumns))
                                <input type="text" value="{{ $v }}" disabled class="mt-1 w-full rounded border border-zinc-200/80 dark:border-white/[0.06] bg-zinc-100 dark:bg-zinc-950/50 px-3 py-2 text-sm text-zinc-500" />
                            @else
                                <input type="text" wire:model="editValues.{{ $k }}" class="mt-1 w-full rounded border border-zinc-200 dark:border-white/10 bg-white px-3 py-2 text-sm text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100" />
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="mt-6 flex gap-2">
                    <button type="button" wire:click="saveEdit" class="inline-flex items-center gap-2 rounded bg-pg-blue-base px-4 py-2 text-sm hover:bg-pg-blue-light">
                        {{ svg('hugeicons-floppy-disk', 'h-4 w-4 shrink-0') }}
                        Save
                    </button>
                    <button type="button" wire:click="$set('showEditModal', false)" class="inline-flex items-center gap-2 rounded border border-zinc-300 px-4 py-2 text-sm text-zinc-800 dark:border-white/15 dark:text-zinc-200">
                        {{ svg('hugeicons-cancel-01', 'h-4 w-4 shrink-0') }}
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
