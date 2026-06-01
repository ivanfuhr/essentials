<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use IvanFuhr\Essentials\Loggers\Github\Tracing\EventHandler;

final class GithubLoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/loggers/github.php', 'loggers.github');

        $this->registerContextDehydration();
    }

    public function boot(): void
    {
        if (! config('loggers.github.enabled', false)) {
            return;
        }

        $this->registerLoggingChannel();

        if ($this->shouldEnableTracing()) {
            Event::subscribe(EventHandler::class);
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../../resources/loggers/github/views' => resource_path('views/vendor/essentials/github'),
            ], 'essentials-loggers-github-views');

            $this->publishes([
                __DIR__.'/../../../config/loggers/github.php' => config_path('loggers/github.php'),
            ], 'essentials-loggers-github-config');
        }
    }

    protected function shouldEnableTracing(): bool
    {
        $packageConfig = config('loggers.github.tracing', []);
        $channelConfig = config('logging.channels.github.tracing', []);

        return (bool) ($packageConfig['enabled'] ?? $channelConfig['enabled'] ?? false);
    }

    protected function registerLoggingChannel(): void
    {
        if (! config('loggers.github.repo') || ! config('loggers.github.token')) {
            return;
        }

        $channel = array_filter([
            'driver' => 'custom',
            'via' => GithubIssueHandlerFactory::class,
            'repo' => config('loggers.github.repo'),
            'token' => config('loggers.github.token'),
            'level' => config('loggers.github.level', 'error'),
            'labels' => config('loggers.github.labels', []),
            'deduplication' => config('loggers.github.deduplication'),
            'buffer' => config('loggers.github.buffer'),
            'signature_generator' => config('loggers.github.signature_generator'),
            'tracing' => $this->normalizeTracingConfig(config('loggers.github.tracing', [])),
        ], fn (mixed $value): bool => $value !== null);

        config(['logging.channels.github' => $channel]);
    }

    /**
     * @param  array<string, mixed>  $tracing
     * @return array<string, mixed>
     */
    protected function normalizeTracingConfig(array $tracing): array
    {
        if (isset($tracing['queries']) && ! is_array($tracing['queries'])) {
            $tracing['queries'] = [
                'enabled' => (bool) $tracing['queries'],
                'limit' => $tracing['query_limit'] ?? 50,
            ];
        }

        if (isset($tracing['outgoing_requests']) && ! is_array($tracing['outgoing_requests'])) {
            $tracing['outgoing_requests'] = [
                'enabled' => (bool) $tracing['outgoing_requests'],
                'limit' => $tracing['outgoing_request_limit'] ?? 20,
            ];
        }

        return $tracing;
    }

    /**
     * Register context dehydration callback to prevent large tracing data
     * from being serialized into job payloads.
     */
    protected function registerContextDehydration(): void
    {
        Context::dehydrating(function ($context) {
            $keysToForget = [
                'queries',
                'outgoing_requests',
                'breadcrumbs',
                'session',
                'request',
                'livewire',
                'livewire_originating_page',
                'inertia',
            ];

            foreach ($keysToForget as $key) {
                if ($context->has($key)) {
                    $context->forget($key);
                }
            }

            foreach (array_keys($context->all()) as $key) {
                if (str_starts_with($key, 'outgoing_request.')) {
                    $context->forget($key);
                }
            }
        });
    }
}
