<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

function createFakePgDumpScript(): string
{
    $path = storage_path('app/tmp/fake-pg-dump-'.getmypid().'.sh');

    File::ensureDirectoryExists(dirname($path));

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
    $path = storage_path('app/tmp/fake-pg-restore-'.getmypid().'.sh');

    File::ensureDirectoryExists(dirname($path));

    File::put($path, <<<'BASH'
        #!/usr/bin/env bash
        exit 0
        BASH);

    chmod($path, 0755);

    return $path;
}

function configurePgsqlBackup(): void
{
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
        'essentials.backup.disk' => 'local',
        'essentials.backup.directory' => 'backups',
        'essentials.backup.pg_dump_binary' => createFakePgDumpScript(),
        'filesystems.default' => 'local',
        'filesystems.disks.local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],
    ]);

    Storage::fake('local');
}
