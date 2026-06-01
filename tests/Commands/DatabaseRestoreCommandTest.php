<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    configurePgsqlBackup();

    config([
        'essentials.backup.pg_restore_binary' => createFakePgRestoreScript(),
    ]);
});

it('fails when the backup file does not exist on disk', function (): void {
    $exitCode = Artisan::call('db:restore', [
        'backup' => 'missing.dump',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Backup file not found on disk: backups/missing.dump');
});

it('fails when the connection is not pgsql', function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ],
    ]);

    $exitCode = Artisan::call('db:restore', [
        'backup' => 'missing.dump',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('The selected connection must use the "pgsql" driver.');
});

it('restores a backup from the configured disk', function (): void {
    Storage::disk('local')->put('backups/testing.dump', 'backup');

    $exitCode = Artisan::call('db:restore', [
        'backup' => 'testing.dump',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Restore completed successfully.');
});

it('restores a backup from an absolute path', function (): void {
    $absolutePath = storage_path('app/tmp/testing.dump');
    File::ensureDirectoryExists(dirname($absolutePath));
    File::put($absolutePath, 'backup');

    $exitCode = Artisan::call('db:restore', [
        'backup' => $absolutePath,
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Restore completed successfully.')
        ->and(File::exists($absolutePath))->toBeTrue();
});

it('lists available backups when the requested file is missing', function (): void {
    Storage::disk('local')->put('backups/testing-20250101.dump', 'backup');

    $this->artisan('db:restore', [
        'backup' => 'missing',
        '--force' => true,
    ])
        ->expectsOutputToContain('Available backups:')
        ->expectsOutputToContain('backups/testing-20250101.dump')
        ->assertFailed();
});
