<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Support\Facades\Storage;
use IvanFuhr\Essentials\EssentialsServiceProvider;
use Mockery;

class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * Get the application package providers.
     */
    protected function getPackageProviders($app): array
    {
        app()->detectEnvironment(fn (): string => 'production');

        return [
            EssentialsServiceProvider::class,
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Storage::clearResolvedInstances();

        parent::tearDown();
    }
}
