<?php

namespace App\Services;

use App\Models\Connection as PgConnection;
use Illuminate\Support\Facades\DB;
use PDOException;
use RuntimeException;
use Throwable;

class PostgresConnectionService
{
    /**
     * Connection name prefix for dynamic Laravel DB connections.
     */
    private const DYNAMIC_PREFIX = 'pg_profile_';

    /**
     * Register a temporary pgsql connection for the given profile and return its name.
     *
     * @return array{0: string, 1: ?string} [connectionName, driverError]
     */
    public function registerConnection(PgConnection $profile, ?string $databaseOverride = null): array
    {
        if (! extension_loaded('pdo_pgsql')) {
            return [
                $this->dynamicName($profile, $databaseOverride),
                'The pdo_pgsql PHP extension is not loaded. NativeBlade WASM builds often omit it; use a bridge or run this app with native PHP (php artisan serve) for full PostgreSQL support.',
            ];
        }

        $server = $profile->server;
        if ($server === null) {
            throw new RuntimeException('Connection profile is missing its server.');
        }

        $database = $databaseOverride ?? $profile->database;
        $name = $this->dynamicName($profile, $databaseOverride);

        config([
            "database.connections.{$name}" => [
                'driver' => 'pgsql',
                'host' => $server->host,
                'port' => $server->port,
                'database' => $database,
                'username' => $profile->username,
                'password' => $profile->password,
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => 'public',
                'sslmode' => $profile->sslmode,
            ],
        ]);

        DB::purge($name);

        return [$name, null];
    }

    /**
     * Run a callback with a managed connection, then disconnect.
     *
     * @template T
     *
     * @param  callable(\Illuminate\Database\Connection): T  $callback
     * @return array{0: T|null, 1: ?string} [result, error]
     */
    public function withConnection(PgConnection $profile, callable $callback, ?string $databaseOverride = null): array
    {
        [$name, $driverError] = $this->registerConnection($profile, $databaseOverride);

        if ($driverError !== null) {
            return [null, $driverError];
        }

        try {
            $conn = DB::connection($name);
            $conn->getPdo();

            return [$callback($conn), null];
        } catch (PDOException $e) {
            return [null, $e->getMessage()];
        } catch (Throwable $e) {
            return [null, $e->getMessage()];
        } finally {
            DB::purge($name);
        }
    }

    /**
     * Test connectivity and update profile timestamps on success.
     */
    public function testConnection(PgConnection $profile): array
    {
        [$result, $error] = $this->withConnection($profile, function ($conn) {
            $conn->selectOne('select 1 as ok');

            return true;
        });

        if ($error === null && $result === true) {
            $profile->forceFill([
                'last_error' => null,
                'last_connected_at' => now(),
            ])->save();
        } else {
            $profile->forceFill([
                'last_error' => $error,
            ])->save();
        }

        return [
            'ok' => $error === null,
            'error' => $error,
        ];
    }

    private function dynamicName(PgConnection $profile, ?string $databaseOverride = null): string
    {
        $database = $databaseOverride ?? $profile->database;

        return self::DYNAMIC_PREFIX.$profile->getKey().'_'.substr(md5((string) $database), 0, 10);
    }
}
