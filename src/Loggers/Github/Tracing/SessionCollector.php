<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Tracing;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Session;
use IvanFuhr\Essentials\Loggers\Github\Tracing\Concerns\RedactsData;
use IvanFuhr\Essentials\Loggers\Github\Tracing\Contracts\DataCollectorInterface;

final class SessionCollector implements DataCollectorInterface
{
    use RedactsData;

    public function isEnabled(): bool
    {
        $config = config('logging.channels.github.tracing', []);

        return isset($config['session']) && $config['session'];
    }

    /**
     * Collect session data.
     */
    public function collect(): void
    {
        if (! Session::isStarted()) {
            return;
        }

        $data = Arr::except(Session::all(), ['_token', '_flash']);

        $session = [
            'data' => $this->redactPayload($data),
        ];

        $flash = [
            'old' => Session::get('_flash.old', []),
            'new' => Session::get('_flash.new', []),
        ];

        if (! empty($flash['old']) || ! empty($flash['new'])) {
            $session['flash'] = $flash;
        }

        Context::addHidden('session', $session);
    }
}
