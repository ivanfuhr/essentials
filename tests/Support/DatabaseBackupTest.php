<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use IvanFuhr\Essentials\Support\DatabaseBackup;

beforeEach(function (): void {
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
        'filesystems.default' => 'local',
        'filesystems.disks.local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],
    ]);

    Storage::fake('local');
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

    expect(Storage::disk('local')->exists('backups'))->toBeTrue()
        ->and($backup->directory())->toBe('backups')
        ->and($backup->remotePath('test.dump'))->toBe('backups/test.dump');
});

it('uses the default filesystem disk when backup disk is not configured', function (): void {
    config(['essentials.backup.disk' => null]);

    $backup = DatabaseBackup::forConfiguredDisk();

    expect($backup->disk())->toBe(Storage::disk('local'));
});

it('resolves the local path for a backup file', function (): void {
    $backup = DatabaseBackup::forConfiguredDisk();

    expect($backup->path('snapshot.dump'))->toBe(
        $backup->disk()->path('backups/snapshot.dump')
    );
});

it('lists backup files from the configured directory', function (): void {
    Storage::disk('local')->put('backups/recent.dump', 'backup');

    $backup = DatabaseBackup::forConfiguredDisk();

    expect($backup->files())->toBe(['backups/recent.dump']);
});
