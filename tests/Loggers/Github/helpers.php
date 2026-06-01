<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Monolog\Level;
use Monolog\LogRecord;

function createLogRecord(
    string $message = 'Test',
    array $context = [],
    array $extra = [],
    Level $level = Level::Error,
    ?Throwable $exception = null,
    ?string $signature = null,
): LogRecord {
    $context = Arr::has($context, 'exception') ? $context : array_merge($context, ['exception' => $exception]);

    return new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'test',
        level: $level,
        message: $message,
        context: $context,
        extra: array_merge($extra, ['github_issue_signature' => $signature]),
    );
}
