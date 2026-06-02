<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\SelectPrompt;

function ensureTestingDirectoryExists(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    @mkdir($path, 0755, true);

    if (! is_dir($path)) {
        throw new RuntimeException(sprintf('Failed to create testing directory: %s', $path));
    }
}

function createFakePgDumpScript(): string
{
    $path = storage_path('framework/testing/bin-'.getmypid().'/fake-pg-dump.sh');

    ensureTestingDirectoryExists(dirname($path));

    File::put($path, <<<'BASH'
        #!/usr/bin/env bash
        for arg in "$@"; do
          case "$arg" in
            --file=*) echo "backup" > "${arg#--file=}" ;;
          esac
        done
        exit 0
        BASH);

    chmod($path, 0755);

    return $path;
}

function createFakePgRestoreScript(): string
{
    $path = storage_path('framework/testing/bin-'.getmypid().'/fake-pg-restore.sh');

    ensureTestingDirectoryExists(dirname($path));

    File::put($path, <<<'BASH'
        #!/usr/bin/env bash
        exit 0
        BASH);

    chmod($path, 0755);

    return $path;
}

function createFailingPgDumpScript(): string
{
    $path = storage_path('framework/testing/bin-'.getmypid().'/failing-pg-dump.sh');

    ensureTestingDirectoryExists(dirname($path));

    File::put($path, <<<'BASH'
        #!/usr/bin/env bash
        echo "pg_dump failed" >&2
        exit 1
        BASH);

    chmod($path, 0755);

    return $path;
}

function createUnreadablePgDumpScript(): string
{
    $path = storage_path('framework/testing/bin-'.getmypid().'/unreadable-pg-dump.sh');

    ensureTestingDirectoryExists(dirname($path));

    File::put($path, <<<'BASH'
        #!/usr/bin/env bash
        for arg in "$@"; do
          case "$arg" in
            --file=*) echo "backup" > "${arg#--file=}" && chmod 000 "${arg#--file=}" ;;
          esac
        done
        exit 0
        BASH);

    chmod($path, 0755);

    return $path;
}

function createUnsupportedVersionPgRestoreScript(): string
{
    $path = storage_path('framework/testing/bin-'.getmypid().'/unsupported-pg-restore.sh');

    ensureTestingDirectoryExists(dirname($path));

    File::put($path, <<<'BASH'
        #!/usr/bin/env bash
        echo "pg_restore: error: unsupported version" >&2
        exit 1
        BASH);

    chmod($path, 0755);

    return $path;
}

function createFailingPgRestoreScript(): string
{
    $path = storage_path('framework/testing/bin-'.getmypid().'/failing-pg-restore.sh');

    ensureTestingDirectoryExists(dirname($path));

    File::put($path, <<<'BASH'
        #!/usr/bin/env bash
        echo "pg_restore failed" >&2
        exit 1
        BASH);

    chmod($path, 0755);

    return $path;
}

function backupDisk(): Illuminate\Contracts\Filesystem\Filesystem
{
    $diskName = config('essentials.backup.disk');

    if (! is_string($diskName) || $diskName === '') {
        $diskName = config('filesystems.default', 'local');
    }

    return Storage::disk(is_string($diskName) ? $diskName : 'local');
}

function configurePgsqlBackup(): void
{
    $diskName = 'local-'.getmypid();
    $diskRoot = storage_path('framework/testing/disks/'.$diskName);

    config([
        'database.default' => 'pgsql',
        'database.connections.pgsql' => [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => '5432',
            'database' => 'testing',
            'username' => 'postgres',
            'password' => 'secret',
        ],
        'essentials.backup.disk' => $diskName,
        'essentials.backup.directory' => 'backups',
        'essentials.backup.pg_dump_binary' => createFakePgDumpScript(),
        'filesystems.default' => $diskName,
        'filesystems.disks.'.$diskName => [
            'driver' => 'local',
            'root' => $diskRoot,
        ],
    ]);

    Storage::fake($diskName, ['root' => $diskRoot]);
}

function withPromptFallbacks(?callable $confirm = null, ?callable $select = null): void
{
    ConfirmPrompt::fallbackWhen(true);
    SelectPrompt::fallbackWhen(true);

    if ($confirm !== null) {
        ConfirmPrompt::fallbackUsing($confirm);
    }

    if ($select !== null) {
        SelectPrompt::fallbackUsing($select);
    }
}

function resetPromptFallbacks(): void
{
    foreach ([ConfirmPrompt::class, SelectPrompt::class] as $promptClass) {
        $reflection = new ReflectionClass($promptClass);

        $shouldFallback = $reflection->getProperty('shouldFallback');
        $shouldFallback->setValue(null, false);

        $fallbacks = $reflection->getProperty('fallbacks');
        $fallbacks->setValue(null, []);
    }
}
