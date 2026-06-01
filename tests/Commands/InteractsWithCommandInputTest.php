<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use IvanFuhr\Essentials\Commands\DatabaseRestoreCommand;
use IvanFuhr\Essentials\Support\DatabaseBackup;
use Laravel\Prompts\SelectPrompt;

beforeEach(function (): void {
    configurePgsqlBackup();

    config([
        'essentials.backup.pg_restore_binary' => createFakePgRestoreScript(),
    ]);
});

afterEach(function (): void {
    resetPromptFallbacks();
});

it('treats an empty backup argument as missing', function (): void {
    backupDisk()->put('backups/testing.dump', 'backup');

    SelectPrompt::fallbackWhen(true);
    SelectPrompt::fallbackUsing(fn (): string => 'backups/testing.dump');

    $command = app(DatabaseRestoreCommand::class);
    $command->setLaravel(app());

    $input = new Symfony\Component\Console\Input\ArrayInput([
        'backup' => '',
    ], $command->getDefinition());

    $inputProperty = new ReflectionProperty($command, 'input');
    $inputProperty->setAccessible(true);
    $inputProperty->setValue($command, $input);

    $method = new ReflectionMethod($command, 'optionalStringArgument');
    $method->setAccessible(true);

    expect($method->invoke($command, 'backup'))->toBeNull();

    $selectMethod = new ReflectionMethod($command, 'selectBackup');
    $selectMethod->setAccessible(true);

    expect($selectMethod->invoke($command, DatabaseBackup::forConfiguredDisk()))->toBe('testing.dump');
});

it('treats an empty connection option as missing', function (): void {
    $exitCode = Artisan::call('db:backup', [
        '--connection' => '',
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Backup created successfully at: backups/testing-');
});
