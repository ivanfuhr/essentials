<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('registers essentials configuration', function (): void {
    expect(config('essentials.backup.directory'))->toBe('backups')
        ->and(config('essentials.backup.retention_days'))->toBe(30);
});

it('registers package commands', function (): void {
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain(
        'db:backup',
        'db:restore',
        'db:backups:prune',
        'translations:extract',
        'make:action',
    );
});

it('publishes essentials configuration with the essentials-config tag', function (): void {
    $this->artisan('vendor:publish', [
        '--tag' => 'essentials-config',
        '--force' => true,
    ])->assertSuccessful();

    expect(config_path('essentials.php'))->toBeFile();
});

it('publishes action stubs with the essentials-stubs tag', function (): void {
    $this->artisan('vendor:publish', [
        '--tag' => 'essentials-stubs',
        '--force' => true,
    ])->assertSuccessful();

    expect(base_path('stubs/action.stub'))->toBeFile();
});
