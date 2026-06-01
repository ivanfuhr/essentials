<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Tracing;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Context;
use IvanFuhr\Essentials\Loggers\Github\Tracing\Concerns\RedactsData;
use IvanFuhr\Essentials\Loggers\Github\Tracing\Contracts\EventDrivenCollectorInterface;

final class QueryCollector implements EventDrivenCollectorInterface
{
    use RedactsData;

    private const int DEFAULT_LIMIT = 10;

    public function __invoke(QueryExecuted $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $config = config('logging.channels.github.tracing.queries', []);

        $limit = $config['limit'] ?? self::DEFAULT_LIMIT;

        $queries = Context::getHidden('queries') ?? [];
        $queries[] = [
            'sql' => $event->sql,
            'bindings' => $this->redactBindings($event->bindings),
            'time' => $event->time,
            'connection' => $event->connectionName,
        ];

        // Keep only the last N queries
        if (count($queries) > $limit) {
            $queries = array_slice($queries, -$limit);
        }

        Context::addHidden('queries', $queries);
    }

    public function isEnabled(): bool
    {
        $config = config('logging.channels.github.tracing.queries', []);

        return isset($config['enabled']) && $config['enabled'];
    }
}
