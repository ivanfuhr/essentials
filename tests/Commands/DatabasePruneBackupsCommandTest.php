<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    configurePgsqlBackup();

    config([
        'essentials.backup.retention_days' => 7,
    ]);
});

it('reports when no backups are found', function (): void {
    $exitCode = Artisan::call('db:backups:prune');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('No backups found for removal.');
});

it('does not prune when retention is zero or negative', function (): void {
    backupDisk()->put('backups/old.dump', 'backup');

    $exitCode = Artisan::call('db:backups:prune', ['--days' => 0]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('No backups will be removed because retention is zero or negative.')
        ->and(backupDisk()->exists('backups/old.dump'))->toBeTrue();
});

it('removes backups older than the retention period', function (): void {
    $disk = backupDisk();
    $disk->put('backups/old.dump', 'old');
    $disk->put('backups/recent.dump', 'recent');

    touch($disk->path('backups/old.dump'), now()->subDays(10)->getTimestamp());
    touch($disk->path('backups/recent.dump'), now()->subDay()->getTimestamp());

    $exitCode = Artisan::call('db:backups:prune');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Prune completed. Files removed: 1')
        ->and($disk->exists('backups/old.dump'))->toBeFalse()
        ->and($disk->exists('backups/recent.dump'))->toBeTrue();
});
