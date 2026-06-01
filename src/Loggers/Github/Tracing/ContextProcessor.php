<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Tracing;

use Illuminate\Support\Facades\Context;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class ContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        // Collect environment data if enabled
        $environmentCollector = new EnvironmentCollector;
        if ($environmentCollector->isEnabled()) {
            $environmentCollector->collect();
        }

        // Collect user data if enabled and not already collected
        $userCollector = new UserDataCollector;
        if ($userCollector->isEnabled() && ! Context::has('user')) {
            $userCollector->collect();
        }

        // Collect session data if enabled
        $sessionCollector = new SessionCollector;
        if ($sessionCollector->isEnabled()) {
            $sessionCollector->collect();
        }

        // Collect breadcrumbs if enabled
        $breadcrumbCollector = new BreadcrumbCollector;
        if ($breadcrumbCollector->isEnabled()) {
            $breadcrumbCollector->collect();
        }

        $contextData = array_merge(Context::all(), Context::allHidden());

        if ($contextData === []) {
            return $record;
        }

        return $record->with(
            context: array_merge($record->context, $contextData)
        );
    }
}
