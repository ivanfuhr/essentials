<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Issues\Formatters;

final class OutgoingRequestFormatter
{
    public function format(?array $requests): string
    {
        if (empty($requests)) {
            return '';
        }

        $output = "```\n";
        foreach ($requests as $request) {
            $method = $request['method'] ?? 'GET';
            $url = $request['url'] ?? '';
            $status = $request['status'] ?? null;
            $duration = $request['duration_ms'] ?? null;

            $output .= "{$method} {$url}";
            if ($status !== null) {
                $output .= " → {$status}";
            }
            if ($duration !== null) {
                $output .= " ({$duration}ms)";
            }
            $output .= "\n";
        }

        return mb_rtrim($output)."\n```";
    }
}
