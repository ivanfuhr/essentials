<?php

declare(strict_types=1);

use IvanFuhr\Essentials\Result\Result;

/**
 * @template TValue
 *
 * @param  TValue  $value
 * @return Result<TValue, never>
 */
function success(mixed $value): Result
{
    return Result::success($value);
}

/**
 * @template TFailure of UnitEnum
 *
 * @param  TFailure  $failure
 * @return Result<never, TFailure>
 */
function fail(UnitEnum $failure): Result
{
    return Result::fail($failure);
}
