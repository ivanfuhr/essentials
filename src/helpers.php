<?php

declare(strict_types=1);

use IvanFuhr\Essentials\Result\Result;

function success(mixed $value): Result
{
    return Result::success($value);
}

function fail(UnitEnum $failure): Result
{
    return Result::fail($failure);
}
