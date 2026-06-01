<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Issues\Formatters;

use Illuminate\Support\Arr;

final class ContextFormatter
{
    public function format(array $context): string
    {
        // Exclude exception and sections that are rendered separately
        $context = Arr::except($context, [
            'exception',
            'environment',
            'request',
            'route',
            'route_summary',
            'user',
            'queries',
            'job',
            'command',
            'outgoing_requests',
            'breadcrumbs',
            'session',
            'livewire',
            'livewire_originating_page',
            'inertia',
        ]);

        if (empty($context)) {
            return '';
        }

        return json_encode($context, JSON_PRETTY_PRINT);
    }
}
