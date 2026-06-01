<?php

declare(strict_types=1);

namespace Tests\Support;

use IvanFuhr\Essentials\Loggers\Github\Tracing\CallerFrameProcessor;
use Monolog\LogRecord;

function processLogRecordThroughCallerProbe(CallerFrameProcessor $processor, LogRecord $record): LogRecord
{
    return $processor($record);
}

/**
 * @return array{file: string, func: string}|null
 */
function findCallerFrameThroughProbe(CallerFrameProcessor $processor): ?array
{
    $method = (new \ReflectionClass($processor))->getMethod('findCallerFrame');
    $method->setAccessible(true);

    return $method->invoke($processor);
}
