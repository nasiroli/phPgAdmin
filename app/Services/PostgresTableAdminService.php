<?php

namespace App\Services;

use App\Models\Connection as PgConnection;
use App\Support\PostgresIdentifier;
use Illuminate\Database\Connection;
use InvalidArgumentException;

class PostgresTableAdminService
{
    public function __construct(
        private PostgresConnectionService $connections,
        private PostgresCatalogService $catalog,
    ) {}

    /**
     * @param  list<array{name:string,type:string,nullable:bool,default:?string}> $columns
     * @return array{ok:bool,error:?string}
     */
    public function createTable(PgConnection $profile, string $database, string $schema, string $table, array $columns): array
    {
        PostgresIdentifier::assertValid($schema, 'Schema');
        PostgresIdentifier::assertValid($table, 'Table');
        if ([] === $columns) {
            return ['ok' => false, 'error' => 'Add at least one column.'];
        }

        $parts = [];
        foreach ($columns as $col) {
            PostgresIdentifier::assertValid($col['name'], 'Column');
            if (! $this->isAllowedDataType($col['type'])) {
                return ['ok' => false, 'error' => 'Invalid data type: '.$col['type']];
            }
            $null = ($col['nullable'] ?? true) ? 'NULL' : 'NOT NULL';
            $def = '';
            if (isset($col['default']) && null !== $col['default'] && '' !== $col['default']) {
                try {
                    $def = ' DEFAULT '.$this->sanitizeDefaultExpression($col['default']);
                } catch (InvalidArgumentException $e) {
                    return ['ok' => false, 'error' => $e->getMessage()];
                }
            }
            $parts[] = PostgresIdentifier::quote($col['name']).' '.$col['type'].' '.$null.$def;
        }

        $sql = 'CREATE TABLE '.PostgresIdentifier::qualified($schema, $table).' ('.implode(', ', $parts).')';

        return $this->runDdl($profile, $database, $sql);
    }

    /**
     * @return array{ok:bool,error:?string}
     */
    public function dropTable(PgConnection $profile, string $database, string $schema, string $table): array
    {
        PostgresIdentifier::assertValid($schema, 'Schema');
        PostgresIdentifier::assertValid($table, 'Table');
        $sql = 'DROP TABLE IF EXISTS '.PostgresIdentifier::qualified($schema, $table).' CASCADE';

        return $this->runDdl($profile, $database, $sql);
    }

    /**
     * @return array{ok:bool,error:?string}
     */
    public function truncateTable(PgConnection $profile, string $database, string $schema, string $table): array
    {
        PostgresIdentifier::assertValid($schema, 'Schema');
        PostgresIdentifier::assertValid($table, 'Table');
        $sql = 'TRUNCATE TABLE '.PostgresIdentifier::qualified($schema, $table).' RESTART IDENTITY';

        return $this->runDdl($profile, $database, $sql);
    }

    /**
     * @return array{ok:bool,error:?string}
     */
    public function addColumn(
        PgConnection $profile,
        string $database,
        string $schema,
        string $table,
        string $column,
        string $dataType,
        bool $nullable,
        ?string $default
    ): array {
        PostgresIdentifier::assertValid($schema, 'Schema');
        PostgresIdentifier::assertValid($table, 'Table');
        PostgresIdentifier::assertValid($column, 'Column');
        if (! $this->isAllowedDataType($dataType)) {
            return ['ok' => false, 'error' => 'Invalid data type.'];
        }

        $null = $nullable ? 'NULL' : 'NOT NULL';
        $def = '';
        if (null !== $default && '' !== $default) {
            try {
                $def = ' DEFAULT '.$this->sanitizeDefaultExpression($default);
            } catch (InvalidArgumentException $e) {
                return ['ok' => false, 'error' => $e->getMessage()];
            }
        }

        $sql = 'ALTER TABLE '.PostgresIdentifier::qualified($schema, $table).
            ' ADD COLUMN '.PostgresIdentifier::quote($column).' '.$dataType.' '.$null.$def;

        return $this->runDdl($profile, $database, $sql);
    }

    /**
     * @return array{ok:bool,error:?string}
     */
    public function dropColumn(PgConnection $profile, string $database, string $schema, string $table, string $column): array
    {
        PostgresIdentifier::assertValid($schema, 'Schema');
        PostgresIdentifier::assertValid($table, 'Table');
        PostgresIdentifier::assertValid($column, 'Column');
        $sql = 'ALTER TABLE '.PostgresIdentifier::qualified($schema, $table).
            ' DROP COLUMN '.PostgresIdentifier::quote($column);

        return $this->runDdl($profile, $database, $sql);
    }

    /**
     * @return array{ok:bool,error:?string}
     */
    public function createIndex(
        PgConnection $profile,
        string $database,
        string $schema,
        string $table,
        string $indexName,
        string $columnsCsv
    ): array {
        PostgresIdentifier::assertValid($schema, 'Schema');
        PostgresIdentifier::assertValid($table, 'Table');
        PostgresIdentifier::assertValid($indexName, 'Index name');
        $cols = array_map('trim', explode(',', $columnsCsv));
        foreach ($cols as $c) {
            PostgresIdentifier::assertValid($c, 'Index column');
        }
        $colList = implode(', ', array_map(fn ($c) => PostgresIdentifier::quote($c), $cols));
        $sql = 'CREATE INDEX '.PostgresIdentifier::quote($indexName).' ON '.
            PostgresIdentifier::qualified($schema, $table).' ('.$colList.')';

        return $this->runDdl($profile, $database, $sql);
    }

    /**
     * @return array{ok:bool,error:?string}
     */
    public function dropIndex(PgConnection $profile, string $database, string $schema, string $indexName): array
    {
        PostgresIdentifier::assertValid($schema, 'Schema');
        PostgresIdentifier::assertValid($indexName, 'Index name');
        $sql = 'DROP INDEX IF EXISTS '.PostgresIdentifier::qualified($schema, $indexName);

        return $this->runDdl($profile, $database, $sql);
    }

    /**
     * @param  array<string, string|null>   $data column => raw value string or null
     * @return array{ok:bool,error:?string}
     */
    public function insertRow(PgConnection $profile, string $database, string $schema, string $table, array $data): array
    {
        PostgresIdentifier::assertValid($schema, 'Schema');
        PostgresIdentifier::assertValid($table, 'Table');
        [$colsMeta, $err] = $this->catalog->listColumns($profile, $database, $schema, $table);
        if (null !== $err) {
            return ['ok' => false, 'error' => $err];
        }
        $allowed = [];
        foreach ($colsMeta as $c) {
            $allowed[$c->column_name] = true;
        }
        $columns = [];
        $placeholders = [];
        $bindings = [];
        foreach ($data as $key => $value) {
            if (! isset($allowed[$key])) {
                continue;
            }
            PostgresIdentifier::assertValid($key, 'Column');
            $columns[] = PostgresIdentifier::quote($key);
            $placeholders[] = '?';
            $bindings[] = '' === $value ? null : $value;
        }
        if ([] === $columns) {
            return ['ok' => false, 'error' => 'No valid columns to insert.'];
        }

        $sql = 'INSERT INTO '.PostgresIdentifier::qualified($schema, $table).
            ' ('.implode(', ', $columns).') VALUES ('.implode(', ', $placeholders).')';

        return $this->runInsert($profile, $database, $sql, $bindings);
    }

    /**
     * @param  array<string, string|null>   $data
     * @param  array<string, string|null>   $wherePk primary key column => value
     * @return array{ok:bool,error:?string}
     */
    public function updateRow(PgConnection $profile, string $database, string $schema, string $table, array $data, array $wherePk): array
    {
        PostgresIdentifier::assertValid($schema, 'Schema');
        PostgresIdentifier::assertValid($table, 'Table');
        [$pkCols, $err] = $this->catalog->primaryKeyColumns($profile, $database, $schema, $table);
        if (null !== $err) {
            return ['ok' => false, 'error' => $err];
        }
        if ([] === $pkCols) {
            return ['ok' => false, 'error' => 'Table has no primary key; cannot update rows safely.'];
        }

        $sets = [];
        $bindings = [];
        foreach ($data as $col => $value) {
            if (in_array($col, $pkCols, true)) {
                continue;
            }
            PostgresIdentifier::assertValid($col, 'Column');
            $sets[] = PostgresIdentifier::quote($col).' = ?';
            $bindings[] = '' === $value ? null : $value;
        }
        if ([] === $sets) {
            return ['ok' => false, 'error' => 'Nothing to update.'];
        }

        $wheres = [];
        foreach ($pkCols as $pk) {
            if (! array_key_exists($pk, $wherePk)) {
                return ['ok' => false, 'error' => 'Missing primary key value for '.$pk];
            }
            $wheres[] = PostgresIdentifier::quote($pk).' = ?';
            $bindings[] = $wherePk[$pk];
        }

        $sql = 'UPDATE '.PostgresIdentifier::qualified($schema, $table).' SET '.implode(', ', $sets).' WHERE '.implode(' AND ', $wheres);

        return $this->runUpdate($profile, $database, $sql, $bindings);
    }

    /**
     * @param  list<array<string, string|null>> $wherePks list of pk column => value maps
     * @return array{ok:bool,error:?string}
     */
    public function deleteRows(PgConnection $profile, string $database, string $schema, string $table, array $wherePks): array
    {
        PostgresIdentifier::assertValid($schema, 'Schema');
        PostgresIdentifier::assertValid($table, 'Table');
        [$pkCols, $err] = $this->catalog->primaryKeyColumns($profile, $database, $schema, $table);
        if (null !== $err) {
            return ['ok' => false, 'error' => $err];
        }
        if ([] === $pkCols) {
            return ['ok' => false, 'error' => 'Table has no primary key; cannot delete rows safely.'];
        }

        foreach ($wherePks as $row) {
            $wheres = [];
            $bindings = [];
            foreach ($pkCols as $pk) {
                if (! array_key_exists($pk, $row)) {
                    return ['ok' => false, 'error' => 'Missing PK '.$pk];
                }
                $wheres[] = PostgresIdentifier::quote($pk).' = ?';
                $bindings[] = $row[$pk];
            }
            $sql = 'DELETE FROM '.PostgresIdentifier::qualified($schema, $table).' WHERE '.implode(' AND ', $wheres);
            $r = $this->runDelete($profile, $database, $sql, $bindings);
            if (! $r['ok']) {
                return $r;
            }
        }

        return ['ok' => true, 'error' => null];
    }

    private function runDdl(PgConnection $profile, string $database, string $sql): array
    {
        [$_, $err] = $this->connections->withConnection($profile, function (Connection $conn) use ($sql) {
            $conn->statement($sql);

            return true;
        }, $database);

        return null === $err ? ['ok' => true, 'error' => null] : ['ok' => false, 'error' => $err];
    }

    /**
     * @param  list<string|int|float|null>  $bindings
     * @return array{ok:bool,error:?string}
     */
    private function runInsert(PgConnection $profile, string $database, string $sql, array $bindings): array
    {
        [$_, $err] = $this->connections->withConnection($profile, function (Connection $conn) use ($sql, $bindings) {
            $conn->insert($sql, $bindings);

            return true;
        }, $database);

        return null === $err ? ['ok' => true, 'error' => null] : ['ok' => false, 'error' => $err];
    }

    /**
     * @param  list<string|int|float|null>  $bindings
     * @return array{ok:bool,error:?string}
     */
    private function runUpdate(PgConnection $profile, string $database, string $sql, array $bindings): array
    {
        [$_, $err] = $this->connections->withConnection($profile, function (Connection $conn) use ($sql, $bindings) {
            $conn->update($sql, $bindings);

            return true;
        }, $database);

        return null === $err ? ['ok' => true, 'error' => null] : ['ok' => false, 'error' => $err];
    }

    /**
     * @param  list<string|int|float|null>  $bindings
     * @return array{ok:bool,error:?string}
     */
    private function runDelete(PgConnection $profile, string $database, string $sql, array $bindings): array
    {
        [$_, $err] = $this->connections->withConnection($profile, function (Connection $conn) use ($sql, $bindings) {
            $conn->delete($sql, $bindings);

            return true;
        }, $database);

        return null === $err ? ['ok' => true, 'error' => null] : ['ok' => false, 'error' => $err];
    }

    private function isAllowedDataType(string $type): bool
    {
        $t = trim($type);

        return (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9_]*(?:\(\s*\d+(?:\s*,\s*\d+)?\s*\))?$/', $t);
    }

    private function sanitizeDefaultExpression(string $raw): string
    {
        $t = trim($raw);
        if ('NULL' === strtoupper($t)) {
            return 'NULL';
        }
        if (preg_match('/^-?\d+(\.\d+)?$/', $t)) {
            return $t;
        }
        if (preg_match("/^'(?:[^']|'')*'$/", $t)) {
            return $t;
        }
        if (preg_match('/^(true|false)$/i', $t)) {
            return strtoupper($t);

        }
        if (preg_match('/^current_timestamp$/i', $t)) {
            return 'CURRENT_TIMESTAMP';
        }
        if (preg_match('/^now\(\)$/i', $t)) {
            return 'now()';
        }

        throw new InvalidArgumentException('Default value not allowed. Use NULL, number, quoted string, true/false, CURRENT_TIMESTAMP, or now().');
    }
}
