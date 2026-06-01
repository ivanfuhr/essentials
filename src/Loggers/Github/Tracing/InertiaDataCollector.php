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
 * Inertia.js data collector.
 *
 * This collector detects Inertia requests by examining request headers,
 * without requiring the Inertia package as a dependency. It captures
 * component and version information from the request structure.
 */
final class InertiaDataCollector implements EventDrivenCollectorInterface
{
    use RedactsData;
    use ResolvesTracingConfig;

    public function __invoke(RequestHandled $event): void
    {
        if (! $this->isInertiaRequest($event->request)) {
            return;
        }

        $this->captureFromRequest($event->request, $event->response);
    }

    public function isEnabled(): bool
    {
        return $this->isTracingFeatureEnabled('inertia');
    }

    /**
     * Detect if the current request is an Inertia request.
     */
    private function isInertiaRequest(Request $request): bool
    {
        return $request->hasHeader('X-Inertia');
    }

    /**
     * Capture Inertia request data.
     *
     * @param  \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\Response  $response
     */
    private function captureFromRequest(Request $request, $response): void
    {
        $data = [
            'version' => $request->header('X-Inertia-Version'),
            'partial_reload' => $request->hasHeader('X-Inertia-Partial-Data'),
        ];

        // Capture partial reload details
        if ($data['partial_reload']) {
            $data['partial_component'] = $request->header('X-Inertia-Partial-Component');
            $data['partial_keys'] = $this->parsePartialKeys(
                $request->header('X-Inertia-Partial-Data')
            );
            $data['partial_except'] = $this->parsePartialKeys(
                $request->header('X-Inertia-Partial-Except')
            );
        }

        // Try to extract component name from response
        $component = $this->extractComponentFromResponse($response);
        if ($component !== null) {
            $data['component'] = $component;
        }

        // Add request URL for context
        $data['url'] = $request->fullUrl();

        // Filter nulls but keep false values (like partial_reload = false)
        Context::add('inertia', array_filter($data, fn ($value): bool => $value !== null));
    }

    /**
     * Parse comma-separated partial keys into an array.
     *
     * @return array<string>|null
     */
    private function parsePartialKeys(?string $keys): ?array
    {
        if ($keys === null || $keys === '') {
            return null;
        }

        return array_map(trim(...), explode(',', $keys));
    }

    /**
     * Extract the Inertia component name from the response.
     *
     * @param  \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\Response  $response
     */
    private function extractComponentFromResponse($response): ?string
    {
        // Check X-Inertia header in response
        if (! $response->headers->has('X-Inertia')) {
            return null;
        }

        // Try to parse component from JSON response
        $content = $response->getContent();
        if ($content === false || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return null;
        }

        return $decoded['component'] ?? null;
    }
}
