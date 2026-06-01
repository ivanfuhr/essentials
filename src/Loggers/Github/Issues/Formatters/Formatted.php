<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Issues\Formatters;

final readonly class Formatted
{
    public function __construct(
        public string $title,
        public string $body,
        public string $comment,
    ) {}
}
