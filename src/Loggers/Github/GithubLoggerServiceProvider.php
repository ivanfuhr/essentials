<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use IvanFuhr\Essentials\Loggers\Github\Tracing\EventHandler;

final class GithubLoggerServiceProvider extends ServiceProvider
{
    private const string CONFIG_KEY = 'essentials.loggers.github';

    public function register(): void
    {
        $this->registerContextDehydration();
    }

    public function boot(): void
    {
        if (! config(self::CONFIG_KEY.'.enabled', false)) {
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
        }
    }

    private function shouldEnableTracing(): bool
    {
        $packageConfig = config(self::CONFIG_KEY.'.tracing', []);
        $channelConfig = config('logging.channels.github.tracing', []);

        return (bool) ($packageConfig['enabled'] ?? $channelConfig['enabled'] ?? false);
    }

    private function registerLoggingChannel(): void
    {
        if (! config(self::CONFIG_KEY.'.repo') || ! config(self::CONFIG_KEY.'.token')) {
            return;
        }

        $channel = array_filter([
            'driver' => 'custom',
            'via' => GithubIssueHandlerFactory::class,
            'repo' => config(self::CONFIG_KEY.'.repo'),
            'token' => config(self::CONFIG_KEY.'.token'),
            'level' => config(self::CONFIG_KEY.'.level', 'error'),
            'labels' => config(self::CONFIG_KEY.'.labels', []),
            'deduplication' => config(self::CONFIG_KEY.'.deduplication'),
            'buffer' => config(self::CONFIG_KEY.'.buffer'),
            'signature_generator' => config(self::CONFIG_KEY.'.signature_generator'),
            'tracing' => $this->normalizeTracingConfig(config(self::CONFIG_KEY.'.tracing', [])),
        ], fn (mixed $value): bool => $value !== null);

        config(['logging.channels.github' => $channel]);
    }

    /**
     * @param  array<string, mixed>  $tracing
     * @return array<string, mixed>
     */
    private function normalizeTracingConfig(array $tracing): array
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
    private function registerContextDehydration(): void
    {
        Context::dehydrating(function ($context): void {
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
