<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Tracing\Concerns;

trait ResolvesTracingConfig
{
    /**
     * Get a tracing configuration value.
     *
     * Checks both loggers.github.tracing and logging.channels.github.tracing
     * to support both configuration styles. Package config takes precedence.
     */
    protected function getTracingConfig(string $key, mixed $default = null): mixed
    {
        // Package config takes precedence
        $packageValue = config("loggers.github.tracing.{$key}");
        if ($packageValue !== null) {
            return $packageValue;
        }

        // Fall back to channel config
        return config("logging.channels.github.tracing.{$key}", $default);
    }

    /**
     * Check if a tracing feature is enabled.
     */
    protected function isTracingFeatureEnabled(string $feature): bool
    {
        return (bool) $this->getTracingConfig($feature, false);
    }
}
