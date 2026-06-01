<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use IvanFuhr\Essentials\Support\DatabaseBackup;

beforeEach(function (): void {
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
        'filesystems.default' => $diskName,
        'filesystems.disks.'.$diskName => [
            'driver' => 'local',
            'root' => $diskRoot,
        ],
    ]);

    Storage::fake($diskName, ['root' => $diskRoot]);
});

it('resolves a pgsql connection', function (): void {
    $connection = DatabaseBackup::resolvePgsqlConnection(null);

    expect($connection->database)->toBe('testing')
        ->and($connection->host)->toBe('127.0.0.1')
        ->and($connection->username)->toBe('postgres')
        ->and($connection->password)->toBe('secret');
});

it('throws when the connection is not pgsql', function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ],
    ]);

    DatabaseBackup::resolvePgsqlConnection(null);
})->throws(InvalidArgumentException::class, 'The selected connection must use the "pgsql" driver.');

it('throws when the connection does not exist', function (): void {
    DatabaseBackup::resolvePgsqlConnection('missing');
})->throws(InvalidArgumentException::class, 'Connection "missing" not found.');

it('creates the backup directory on the configured disk', function (): void {
    $backup = DatabaseBackup::forConfiguredDisk();

    expect(backupDisk()->exists('backups'))->toBeTrue()
        ->and($backup->directory())->toBe('backups')
        ->and($backup->remotePath('test.dump'))->toBe('backups/test.dump');
});

it('uses the default filesystem disk when backup disk is not configured', function (): void {
    config(['essentials.backup.disk' => null]);

    $backup = DatabaseBackup::forConfiguredDisk();

    expect($backup->disk()->path('probe'))->toBe(backupDisk()->path('probe'));
});

it('resolves the local path for a backup file', function (): void {
    $backup = DatabaseBackup::forConfiguredDisk();

    expect($backup->path('snapshot.dump'))->toBe(
        $backup->disk()->path('backups/snapshot.dump')
    );
});

it('lists backup files from the configured directory', function (): void {
    backupDisk()->put('backups/recent.dump', 'backup');

    $backup = DatabaseBackup::forConfiguredDisk();

    expect($backup->files())->toBe(['backups/recent.dump']);
});

it('throws when the configured disk is not a filesystem adapter', function (): void {
    config(['essentials.backup.disk' => 'invalid']);

    Storage::partialMock()
        ->shouldReceive('disk')
        ->with('invalid')
        ->andReturn(Mockery::mock(Illuminate\Contracts\Filesystem\Filesystem::class));

    DatabaseBackup::forConfiguredDisk();
})->throws(InvalidArgumentException::class, 'Disk "invalid" is not a filesystem adapter.');
