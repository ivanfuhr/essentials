<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Issues\Formatters;

final class Formatted
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $comment,
    ) {}
}
