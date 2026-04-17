<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class AppSetupService
{
    public function isComplete(): bool
    {
        return File::exists(config('setup.lock_path'));
    }

    /**
     * Apply password and run migrations, key, NativeBlade config, optional npm build.
     *
     * @return array{npm_build_ok: bool, npm_build_message: string|null}
     */
    public function run(string $plainPassword): array
    {
        $envExample = base_path('.env.example');
        $envPath = base_path('.env');

        if (! File::exists($envExample)) {
            throw new \RuntimeException('.env.example is missing.');
        }

        if (! File::exists($envPath)) {
            File::copy($envExample, $envPath);
        }

        $this->mergeEnvValues($envPath, [
            'APP_DESKTOP_PASSWORD' => $plainPassword,
        ]);

        Artisan::call('config:clear');

        Artisan::call('key:generate', ['--force' => true]);

        $dbPath = database_path('database.sqlite');
        if (! File::exists($dbPath)) {
            File::put($dbPath, '');
        }

        Artisan::call('migrate', ['--force' => true]);

        try {
            Artisan::call('nativeblade:config');
        } catch (Throwable) {
            //
        }

        File::put(config('setup.lock_path'), now()->toIso8601String()."\n");

        Artisan::call('config:clear');

        return $this->tryNpmBuild();
    }

    /**
     * @param  array<string, string>  $values
     */
    public function mergeEnvValues(string $path, array $values): void
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $keys = array_keys($values);
        $seen = [];
        $out = [];

        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                $out[] = $line;

                continue;
            }

            if (preg_match('/^([A-Z0-9_]+)=(.*)$/s', $line, $m)) {
                $key = $m[1];
                if (array_key_exists($key, $values)) {
                    $out[] = $key.'='.$this->escapeEnvValue((string) $values[$key]);
                    $seen[$key] = true;

                    continue;
                }
            }

            $out[] = $line;
        }

        foreach ($keys as $key) {
            if (! isset($seen[$key])) {
                $out[] = $key.'='.$this->escapeEnvValue((string) $values[$key]);
            }
        }

        File::put($path, implode("\n", $out)."\n");
    }

    private function escapeEnvValue(string $value): string
    {
        if (preg_match('/^[\w.\-]+$/', $value)) {
            return $value;
        }

        return '"'.addcslashes($value, "\\\"\n\r\t\$").'"';
    }

    /**
     * @return array{npm_build_ok: bool, npm_build_message: string|null}
     */
    private function tryNpmBuild(): array
    {
        try {
            $process = new Process(
                ['npm', 'run', 'build'],
                base_path(),
                null,
                null,
                600
            );
            $process->run();

            if ($process->isSuccessful()) {
                return ['npm_build_ok' => true, 'npm_build_message' => null];
            }

            return [
                'npm_build_ok' => false,
                'npm_build_message' => trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'npm run build failed.',
            ];
        } catch (Throwable $e) {
            return [
                'npm_build_ok' => false,
                'npm_build_message' => $e->getMessage(),
            ];
        }
    }
}
