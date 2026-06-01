<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    configurePgsqlBackup();
});

afterEach(function (): void {
    resetPromptFallbacks();

    foreach (glob(storage_path('app/tmp/testing-*.dump')) ?: [] as $file) {
        @chmod($file, 0644);
        @unlink($file);
    }
});

it('fails when the connection is not pgsql', function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ],
    ]);

    $exitCode = Artisan::call('db:backup');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('The selected connection must use the "pgsql" driver.');
});

it('fails when the connection does not exist', function (): void {
    $exitCode = Artisan::call('db:backup', ['--connection' => 'missing']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Connection "missing" not found.');
});

it('creates a backup on the configured disk', function (): void {
    $exitCode = Artisan::call('db:backup');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Backup created successfully at: backups/testing-')
        ->and(backupDisk()->allFiles('backups'))->not->toBeEmpty();
});

it('creates the temporary directory when it does not exist', function (): void {
    config(['essentials.backup.pg_dump_binary' => createFakePgDumpScript()]);

    $exitCode = Artisan::call('db:backup');

    expect($exitCode)->toBe(0)
        ->and(File::isDirectory(storage_path('app/tmp')))->toBeTrue();
});

it('fails when pg_dump exits with an error', function (): void {
    config(['essentials.backup.pg_dump_binary' => createFailingPgDumpScript()]);

    $exitCode = Artisan::call('db:backup');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('pg_dump failed');
});

it('fails when the temporary backup file cannot be opened', function (): void {
    config(['essentials.backup.pg_dump_binary' => createUnreadablePgDumpScript()]);

    $exitCode = Artisan::call('db:backup');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Failed to open temporary file for upload.');
});
