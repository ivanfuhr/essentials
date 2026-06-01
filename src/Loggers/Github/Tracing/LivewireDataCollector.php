<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Tracing;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use IvanFuhr\Essentials\Loggers\Github\Tracing\Concerns\RedactsData;
use IvanFuhr\Essentials\Loggers\Github\Tracing\Concerns\ResolvesTracingConfig;
use IvanFuhr\Essentials\Loggers\Github\Tracing\Contracts\EventDrivenCollectorInterface;

/**
 * Livewire data collector (v3+ only).
 *
 * This collector detects Livewire requests by examining request headers and payload,
 * without requiring the Livewire package as a dependency. It captures component data
 * from the request structure.
 */
final class LivewireDataCollector implements EventDrivenCollectorInterface
{
    use RedactsData;
    use ResolvesTracingConfig;

    /**
     * Maximum number of keys to include from component state.
     */
    private const int MAX_STATE_KEYS = 50;

    /**
     * Maximum serialized size (in bytes) for component state.
     */
    private const int MAX_STATE_SIZE = 8192;

    public function __invoke(RequestHandled $event): void
    {
        if (! $this->isLivewireRequest($event->request)) {
            return;
        }

        $this->captureFromRequest($event->request);
    }

    public function isEnabled(): bool
    {
        return $this->isTracingFeatureEnabled('livewire');
    }

    /**
     * Detect if the current request is a Livewire request.
     */
    private function isLivewireRequest(Request $request): bool
    {
        // Livewire 3+ sends X-Livewire header
        if ($request->hasHeader('X-Livewire')) {
            return true;
        }

        // Also check for Livewire update endpoint
        $path = $request->path();

        return str_contains($path, 'livewire/update')
            || str_contains($path, 'livewire/message');
    }

    /**
     * Capture Livewire component data from the request.
     */
    private function captureFromRequest(Request $request): void
    {
        $payload = $request->json()->all();
        $components = $this->extractComponents($payload);

        if ($components === []) {
            return;
        }

        $data = [
            'components' => $components,
            'url' => $request->fullUrl(),
        ];

        // Extract originating page from the first component
        $originatingPage = $this->resolveOriginatingPage($payload, $request);
        if ($originatingPage !== null) {
            $data['originating_page'] = $originatingPage;
            Context::add('livewire_originating_page', $originatingPage);
        }

        Context::add('livewire', $this->redactPayload($data));
    }

    /**
     * Extract component information from the Livewire payload.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractComponents(array $payload): array
    {
        $components = [];

        // Livewire 3 structure: components array with snapshot
        foreach ($payload['components'] ?? [] as $component) {
            $snapshot = is_string($component['snapshot'] ?? null)
                ? $this->decodeSnapshot($component['snapshot'])
                : ($component['snapshot'] ?? []);

            $memo = $snapshot['memo'] ?? [];

            $componentData = [
                'name' => $memo['name'] ?? null,
                'id' => $memo['id'] ?? null,
                'path' => $memo['path'] ?? null,
            ];

            // Extract component state from snapshot data
            $state = $snapshot['data'] ?? [];
            if (! empty($state)) {
                $componentData['state'] = $this->truncateState($state);
            }

            // Extract method calls with parameters
            $calls = $component['calls'] ?? [];
            if (! empty($calls)) {
                $componentData['methods'] = collect($calls)
                    ->filter(fn ($call): bool => isset($call['method']))
                    ->map(fn ($call): array => [
                        'method' => $call['method'],
                        'params' => $call['params'] ?? [],
                    ])
                    ->values()
                    ->toArray();
            }

            // Extract updated properties with values (wire:model updates)
            $updates = $component['updates'] ?? [];
            if (! empty($updates)) {
                $componentData['updates'] = $updates;
            }

            $components[] = array_filter($componentData);
        }

        return $components;
    }

    /**
     * Truncate component state to prevent excessive data capture.
     *
     * Limits both the number of keys and the total serialized size.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function truncateState(array $state): array
    {
        $totalKeys = count($state);

        // Limit number of keys
        if ($totalKeys > self::MAX_STATE_KEYS) {
            $state = array_slice($state, 0, self::MAX_STATE_KEYS, true);
            $state['__truncated'] = sprintf('Showing %d of %d keys', self::MAX_STATE_KEYS, $totalKeys);
        }

        // Check serialized size and trim if needed
        $serialized = json_encode($state);
        if ($serialized !== false && mb_strlen($serialized) > self::MAX_STATE_SIZE) {
            $truncated = [];
            $currentSize = 2; // account for {} wrapper

            foreach ($state as $key => $value) {
                $encoded = json_encode([$key => $value]);
                $entrySize = $encoded !== false ? mb_strlen($encoded) - 2 : 0; // subtract {} wrapper

                if ($currentSize + $entrySize > self::MAX_STATE_SIZE) {
                    $truncated['__truncated'] = sprintf('State truncated from %d bytes to ~%d bytes', mb_strlen($serialized), $currentSize);
                    break;
                }

                $truncated[$key] = $value;
                $currentSize += $entrySize + 1; // +1 for comma separator
            }

            return $truncated;
        }

        return $state;
    }

    /**
     * Decode a JSON-encoded snapshot string.
     *
     * @return array<string, mixed>
     */
    private function decodeSnapshot(string $snapshot): array
    {
        $decoded = json_decode($snapshot, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Resolve the originating page the user was on.
     */
    private function resolveOriginatingPage(array $payload, Request $request): ?string
    {
        // Try to get path from first component's snapshot
        $firstComponent = $payload['components'][0] ?? null;
        if ($firstComponent) {
            $snapshot = $firstComponent['snapshot'] ?? [];
            $memo = is_string($snapshot)
                ? $this->decodeSnapshot($snapshot)['memo'] ?? []
                : $snapshot['memo'] ?? [];

            if (isset($memo['path'])) {
                return $memo['path'];
            }
        }

        // Legacy: check fingerprint.path
        $fingerprint = $request->input('fingerprint.path');
        if ($fingerprint !== null) {
            return $fingerprint;
        }

        // Fall back to HTTP referer
        $referer = $request->header('referer');
        if ($referer !== null) {
            $parsed = parse_url($referer);

            return ($parsed['path'] ?? '/').
                (isset($parsed['query']) ? '?'.$parsed['query'] : '');
        }

        return null;
    }
}
