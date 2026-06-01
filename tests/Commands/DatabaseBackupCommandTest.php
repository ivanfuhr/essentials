<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    configurePgsqlBackup();
});

afterEach(function (): void {
    File::deleteDirectory(storage_path('app/tmp'));
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
        ->and(Storage::disk('local')->allFiles('backups'))->not->toBeEmpty();
});
