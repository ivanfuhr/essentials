<?php

declare(strict_types=1);

namespace Tests\Loggers\Github;

use Tests\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('essentials.loggers.github.enabled', true);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', [
            'driver' => 'array',
            'serialize' => false,
        ]);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('essentials.loggers.github.tracing', [
            'enabled' => true,
        ]);

        $app['config']->set('logging.channels.github.tracing', [
            'enabled' => true,
            'requests' => true,
            'user' => true,
        ]);
    }
}
