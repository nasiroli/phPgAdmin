<?php

namespace App\Services;

use App\Models\Connection as PgConnection;
use App\Support\PostgresIdentifier;
use Illuminate\Database\Connection;

class PostgresCatalogService
{
    public function __construct(
        private PostgresConnectionService $connections,
    ) {}

    /**
     * @return array{0: list<string>, 1: ?string}
     */
    public function listDatabases(PgConnection $profile, ?string $databaseOverride = null): array
    {
        return $this->connections->withConnection($profile, function (Connection $conn) {
            $rows = $conn->select('select datname from pg_database where datistemplate = false order by datname');

            return array_map(fn ($r) => $r->datname, $rows);
        }, $databaseOverride);
    }

    /**
     * @return array{0: list<string>, 1: ?string}
     */
    public function listSchemas(PgConnection $profile, string $database): array
    {
        return $this->connections->withConnection($profile, function (Connection $conn) {
            $rows = $conn->select(
                "select schema_name from information_schema.schemata where schema_name not in ('pg_catalog','information_schema','pg_toast') order by schema_name"
            );

            return array_map(fn ($r) => $r->schema_name, $rows);
        }, $database);
    }

    /**
     * @return array{0: list<array{schema:string,name:string,type:string}>, 1: ?string}
     */
    public function listTables(PgConnection $profile, string $database, string $schema): array
    {
        PostgresIdentifier::assertValid($schema, 'Schema');

        return $this->connections->withConnection($profile, function (Connection $conn) use ($schema) {
            $rows = $conn->select(
                'select table_schema as schema, table_name as name, table_type as type from information_schema.tables where table_schema = ? order by table_name',
                [$schema]
            );
            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'schema' => $r->schema,
                    'name'   => $r->name,
                    'type'   => $r->type,
                ];
            }

            return $out;
        }, $database);
    }

    /**
     * @return array{0: list<object>, 1: ?string}
     */
    public function listColumns(PgConnection $profile, string $database, string $schema, string $table): array
    {
        PostgresIdentifier::assertValid($schema, 'Schema');
        PostgresIdentifier::assertValid($table, 'Table');

        return $this->connections->withConnection($profile, function (Connection $conn) use ($schema, $table) {
            return $conn->select(
                'select column_name, data_type, udt_name, is_nullable, column_default, ordinal_position,
                        character_maximum_length, numeric_precision, numeric_scale
                 from information_schema.columns
                 where table_schema = ? and table_name = ?
                 order by ordinal_position',
                [$schema, $table]
            );
        }, $database);
    }

    /**
     * Build a PostgreSQL type fragment suitable for ALTER COLUMN … TYPE from information_schema row.
     */
    public static function columnTypeSqlForAlter(object $row): string
    {
        $dt = (string) ($row->data_type ?? '');
        $udt = (string) ($row->udt_name ?? '');

        if (($dt === 'character varying' || $udt === 'varchar') && isset($row->character_maximum_length) && $row->character_maximum_length !== null) {
            return 'character varying('.(int) $row->character_maximum_length.')';
        }

        if (($dt === 'character' || $udt === 'bpchar') && isset($row->character_maximum_length) && $row->character_maximum_length !== null) {
            return 'character('.(int) $row->character_maximum_length.')';
        }

        if (($dt === 'numeric' || $dt === 'decimal') && isset($row->numeric_precision) && $row->numeric_precision !== null) {
            $p = (int) $row->numeric_precision;
            $s = isset($row->numeric_scale) && $row->numeric_scale !== null ? (int) $row->numeric_scale : 0;

            return $dt.'('.$p.','.$s.')';
        }

        $map = [
            'int2' => 'smallint',
            'int4' => 'integer',
            'int8' => 'bigint',
            'float4' => 'real',
            'float8' => 'double precision',
            'bool' => 'boolean',
            'text' => 'text',
            'json' => 'json',
            'jsonb' => 'jsonb',
            'uuid' => 'uuid',
            'date' => 'date',
            'time' => 'time without time zone',
            'timetz' => 'time with time zone',
            'timestamp' => 'timestamp without time zone',
            'timestamptz' => 'timestamp with time zone',
            'interval' => 'interval',
            'bytea' => 'bytea',
            'varchar' => 'character varying',
        ];

        if (isset($map[$udt])) {
            return $map[$udt];
        }

        if ($dt !== '') {
            return $dt;
        }

        return $udt;
    }

    /**
     * @return array{0: list<string>, 1: ?string}
     */
    public function primaryKeyColumns(PgConnection $profile, string $database, string $schema, string $table): array
    {
        PostgresIdentifier::assertValid($schema, 'Schema');
        PostgresIdentifier::assertValid($table, 'Table');

        return $this->connections->withConnection($profile, function (Connection $conn) use ($schema, $table) {
            $rows = $conn->select(
                'select kcu.column_name
                 from information_schema.table_constraints tc
                 join information_schema.key_column_usage kcu
                   on tc.constraint_name = kcu.constraint_name
                  and tc.table_schema = kcu.table_schema
                  and tc.table_name = kcu.table_name
                 where tc.table_schema = ?
                   and tc.table_name = ?
                   and tc.constraint_type = \'PRIMARY KEY\'
                 order by kcu.ordinal_position',
                [$schema, $table]
            );

            return array_map(fn ($r) => $r->column_name, $rows);
        }, $database);
    }

    /**
     * @return array{0: list<object>, 1: ?string}
     */
    public function listIndexes(PgConnection $profile, string $database, string $schema, string $table): array
    {
        PostgresIdentifier::assertValid($schema, 'Schema');
        PostgresIdentifier::assertValid($table, 'Table');

        return $this->connections->withConnection($profile, function (Connection $conn) use ($schema, $table) {
            return $conn->select(
                'select indexname, indexdef from pg_indexes where schemaname = ? and tablename = ? order by indexname',
                [$schema, $table]
            );
        }, $database);
    }

    /**
     * @return array{0: int, 1: ?string}
     */
    public function countRows(PgConnection $profile, string $database, string $schema, string $table): array
    {
        PostgresIdentifier::assertValid($schema, 'Schema');
        PostgresIdentifier::assertValid($table, 'Table');
        $q = 'select count(*) as c from '.PostgresIdentifier::qualified($schema, $table);

        return $this->connections->withConnection($profile, function (Connection $conn) use ($q) {
            $one = $conn->selectOne($q);

            return (int) ($one->c ?? 0);
        }, $database);
    }

    /**
     * @return array{0: list<object>, 1: ?string}
     */
    public function fetchPage(PgConnection $profile, string $database, string $schema, string $table, int $limit, int $offset): array
    {
        PostgresIdentifier::assertValid($schema, 'Schema');
        PostgresIdentifier::assertValid($table, 'Table');
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        $qualified = PostgresIdentifier::qualified($schema, $table);
        $sql = "select * from {$qualified} limit {$limit} offset {$offset}";

        return $this->connections->withConnection($profile, function (Connection $conn) use ($sql) {
            return $conn->select($sql);
        }, $database);
    }
}
