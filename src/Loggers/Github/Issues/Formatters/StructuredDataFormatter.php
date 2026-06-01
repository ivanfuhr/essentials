<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Issues\Formatters;

final class StructuredDataFormatter
{
    public function format(?array $data): string
    {
        if ($data === null || $data === []) {
            return '';
        }

        return "```json\n".json_encode($data, JSON_PRETTY_PRINT)."\n```";
    }
}
