<?php

declare(strict_types=1);

use IvanFuhr\Essentials\Result\Result;

if (! function_exists('success')) {
    function success(mixed $value): Result
    {
        return Result::success($value);
    }
}

if (! function_exists('fail')) {
    function fail(\UnitEnum $failure): Result
    {
        return Result::fail($failure);
    }
}
