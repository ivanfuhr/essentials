<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Deduplication;

use Monolog\LogRecord;

interface SignatureGeneratorInterface
{
    /**
     * Generate a unique signature for the log record
     */
    public function generate(LogRecord $record): string;
}
