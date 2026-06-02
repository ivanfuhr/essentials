<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use IvanFuhr\Essentials\Commands\DatabaseRestoreCommand;
use IvanFuhr\Essentials\Support\DatabaseBackup;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\SelectPrompt;

beforeEach(function (): void {
    configurePgsqlBackup();

    config([
        'essentials.backup.pg_restore_binary' => createFakePgRestoreScript(),
    ]);
});

afterEach(function (): void {
    Mockery::close();
    resetPromptFallbacks();
    Storage::clearResolvedInstances();
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
    backupDisk()->put('backups/testing.dump', 'backup');

    $exitCode = Artisan::call('db:restore', [
        'backup' => 'testing.dump',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Restore completed successfully.');
});

it('restores a backup from an absolute path', function (): void {
    $absolutePath = storage_path('framework/testing/absolute-'.getmypid().'/testing.dump');
    File::ensureDirectoryExists(dirname($absolutePath));
    File::put($absolutePath, 'backup');

    try {
        $exitCode = Artisan::call('db:restore', [
            'backup' => $absolutePath,
            '--force' => true,
        ]);

        expect($exitCode)->toBe(0)
            ->and(Artisan::output())->toContain('Restore completed successfully.')
            ->and(File::exists($absolutePath))->toBeTrue();
    } finally {
        File::deleteDirectory(dirname($absolutePath));
    }
});

it('lists available backups when the requested file is missing', function (): void {
    backupDisk()->put('backups/testing-20250101.dump', 'backup');

    $exitCode = Artisan::call('db:restore', [
        'backup' => 'missing',
        '--force' => true,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Available backups:')
        ->and($output)->toContain('backups/testing-20250101.dump');
});

it('warns when no backups exist on the configured disk', function (): void {
    $exitCode = Artisan::call('db:restore', [
        'backup' => 'missing.dump',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('No backups found on the configured disk.');
});

it('fails when an absolute backup path does not exist', function (): void {
    $exitCode = Artisan::call('db:restore', [
        'backup' => '/tmp/missing-backup.dump',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Backup file not found: /tmp/missing-backup.dump');
});

it('fails when pg_restore exits with an error', function (): void {
    backupDisk()->put('backups/testing.dump', 'backup');

    config(['essentials.backup.pg_restore_binary' => createFailingPgRestoreScript()]);

    $exitCode = Artisan::call('db:restore', [
        'backup' => 'testing.dump',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('pg_restore failed');
});

it('shows guidance when pg_restore reports an unsupported version', function (): void {
    backupDisk()->put('backups/testing.dump', 'backup');

    config(['essentials.backup.pg_restore_binary' => createUnsupportedVersionPgRestoreScript()]);

    $exitCode = Artisan::call('db:restore', [
        'backup' => 'testing.dump',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('The installed pg_restore is older than the backup file format.');
});

it('fails when the temporary restore file cannot be created', function (): void {
    $filename = 'blocked-'.getmypid().'.dump';
    $temporaryFilename = getmypid().'-'.$filename;
    backupDisk()->put('backups/'.$filename, 'backup');
    File::ensureDirectoryExists(storage_path('app/tmp'));
    File::ensureDirectoryExists(storage_path('app/tmp/'.$temporaryFilename));

    try {
        $exitCode = Artisan::call('db:restore', [
            'backup' => $filename,
            '--force' => true,
        ]);

        expect($exitCode)->toBe(1)
            ->and(Artisan::output())->toContain('Failed to create temporary file for restore.');
    } finally {
        File::deleteDirectory(storage_path('app/tmp/'.$temporaryFilename));
    }
});

it('fails when no backups exist for interactive selection', function (): void {
    $exitCode = Artisan::call('db:restore', ['--force' => true]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('No backups found on the configured disk.');
});

it('creates the temporary directory when restoring from disk', function (): void {
    backupDisk()->put('backups/testing.dump', 'backup');

    File::deleteDirectory(storage_path('app/tmp'));

    config([
        'essentials.backup.pg_restore_binary' => createFakePgRestoreScript(),
        'essentials.backup.pg_dump_binary' => createFakePgDumpScript(),
    ]);

    $exitCode = Artisan::call('db:restore', [
        'backup' => 'testing.dump',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0)
        ->and(File::isDirectory(storage_path('app/tmp')))->toBeTrue();
});

it('cancels restore when confirmation is declined', function (): void {
    backupDisk()->put('backups/testing.dump', 'backup');

    ConfirmPrompt::fallbackWhen(true);
    ConfirmPrompt::fallbackUsing(fn (): bool => false);

    $exitCode = Artisan::call('db:restore', [
        'backup' => 'testing.dump',
        '--no-interaction' => true,
    ]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Restore cancelled.');
});

it('fails when the backup stream cannot be read from disk', function (): void {
    backupDisk()->put('backups/testing.dump', 'backup');

    $diskName = config('essentials.backup.disk');
    $filesystem = Mockery::mock(backupDisk())->makePartial();
    $filesystem->shouldReceive('exists')->with('backups/testing.dump')->andReturn(true);
    $filesystem->shouldReceive('readStream')->with('backups/testing.dump')->andReturn(false);

    Storage::partialMock()
        ->shouldReceive('disk')
        ->with($diskName)
        ->andReturn($filesystem);

    $exitCode = Artisan::call('db:restore', [
        'backup' => 'testing.dump',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Failed to read backup file from disk.');
});

it('selects a backup interactively when none is provided', function (): void {
    backupDisk()->put('backups/testing.dump', 'backup');

    SelectPrompt::fallbackWhen(true);
    SelectPrompt::fallbackUsing(fn (): string => 'backups/testing.dump');

    $command = app(DatabaseRestoreCommand::class);
    $method = new ReflectionMethod($command, 'selectBackup');

    expect($method->invoke($command, DatabaseBackup::forConfiguredDisk()))->toBe('testing.dump');
});

it('skips the safety backup when confirmation is declined', function (): void {
    ConfirmPrompt::fallbackWhen(true);
    ConfirmPrompt::fallbackUsing(fn (): bool => false);

    $command = app(DatabaseRestoreCommand::class);
    $method = new ReflectionMethod($command, 'runSafetyBackup');

    expect($method->invoke($command, null))->toBeTrue();
});

it('aborts when the safety backup fails', function (): void {
    backupDisk()->put('backups/testing.dump', 'backup');

    config(['essentials.backup.pg_dump_binary' => createFailingPgDumpScript()]);

    ConfirmPrompt::fallbackWhen(true);
    ConfirmPrompt::fallbackUsing(fn (): bool => true);

    $exitCode = Artisan::call('db:restore', [
        'backup' => 'testing.dump',
        '--no-interaction' => true,
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Safety backup failed; restore aborted.');
});
