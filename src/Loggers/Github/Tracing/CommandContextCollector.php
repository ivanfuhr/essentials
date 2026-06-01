<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Tracing;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Context;
use IvanFuhr\Essentials\Loggers\Github\Tracing\Concerns\RedactsData;
use IvanFuhr\Essentials\Loggers\Github\Tracing\Concerns\ResolvesTracingConfig;
use IvanFuhr\Essentials\Loggers\Github\Tracing\Contracts\EventDrivenCollectorInterface;

final class CommandContextCollector implements EventDrivenCollectorInterface
{
    use RedactsData;
    use ResolvesTracingConfig;

    public function __invoke(CommandStarting $event): void
    {
        Context::add('command', [
            'name' => $event->command,
            'arguments' => $event->input->getArguments(),
            'options' => $this->redactPayload($event->input->getOptions()),
        ]);
    }

    public function isEnabled(): bool
    {
        return $this->isTracingFeatureEnabled('commands');
    }
}
