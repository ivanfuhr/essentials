<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Issues\Formatters;

final class OutgoingRequestFormatter
{
    public function format(?array $requests): string
    {
        if ($requests === null || $requests === []) {
            return '';
        }

        $output = "```\n";
        foreach ($requests as $request) {
            $method = $request['method'] ?? 'GET';
            $url = $request['url'] ?? '';
            $status = $request['status'] ?? null;
            $duration = $request['duration_ms'] ?? null;

            $output .= sprintf('%s %s', $method, $url);
            if ($status !== null) {
                $output .= ' → '.$status;
            }

            if ($duration !== null) {
                $output .= sprintf(' (%sms)', $duration);
            }

            $output .= "\n";
        }

        return mb_rtrim($output)."\n```";
    }
}
