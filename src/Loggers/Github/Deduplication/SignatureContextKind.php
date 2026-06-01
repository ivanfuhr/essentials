<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Deduplication;

enum SignatureContextKind: string
{
    case Http = 'http';
    case Job = 'job';
    case Command = 'command';
    case Other = 'other';
}
