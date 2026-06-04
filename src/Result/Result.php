<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Result;

use LogicException;
use UnitEnum;

/**
 * @template-covariant TValue
 * @template-covariant TFailure of UnitEnum
 */
final class Result
{
    private bool $handled = false;

    private function __construct(
        private readonly bool $success,
        private readonly mixed $payload,
    ) {}

    /**
     * @template TSuccessValue
     *
     * @param  TSuccessValue  $value
     * @return self<TSuccessValue, never>
     */
    public static function success(mixed $value): self
    {
        /** @var self<TSuccessValue, never> $result */
        $result = new self(true, $value);

        return $result;
    }

    /**
     * @template TFailValue of UnitEnum
     *
     * @param  TFailValue  $failure
     * @return self<never, TFailValue>
     */
    public static function fail(UnitEnum $failure): self
    {
        /** @var self<never, TFailValue> $result */
        $result = new self(false, $failure);

        return $result;
    }

    /**
     * @phpstan-assert-if-true true $this->success
     */
    public function successful(): bool
    {
        return $this->success;
    }

    /**
     * @phpstan-assert-if-true false $this->success
     */
    public function failed(): bool
    {
        return ! $this->success;
    }

    /**
     * @return TValue
     */
    public function value(): mixed
    {
        if ($this->failed()) {
            throw new LogicException('Cannot get the value from a failed result. Check successful() first or use valueOr().');
        }

        return $this->payload;
    }

    /**
     * @template TDefault
     *
     * @param  TDefault  $default
     * @return TValue|TDefault
     */
    public function valueOr(mixed $default): mixed
    {
        if ($this->success) {
            return $this->payload;
        }

        return $default;
    }

    /**
     * @return TFailure
     */
    public function failure(): UnitEnum
    {
        if ($this->success) {
            throw new LogicException('Cannot get the failure from a successful result. Check failed() first or use whenFailed().');
        }

        /** @var TFailure $failure */
        $failure = $this->payload;

        return $failure;
    }

    /**
     * @param  callable(TValue): void  $callback
     * @return self<TValue, TFailure>
     */
    public function whenSuccessful(callable $callback): self
    {
        if ($this->success && ! $this->handled) {
            $callback($this->value());
            $this->handled = true;
        }

        return $this;
    }

    /**
     * @template TExpectedFailure of UnitEnum
     *
     * @param  TExpectedFailure  $expectedFailure
     * @param  callable(): void  $callback
     * @return self<TValue, TFailure>
     */
    public function whenFailed(UnitEnum $expectedFailure, callable $callback): self
    {
        if ($this->failed() && ! $this->handled && $this->failureMatches($expectedFailure)) {
            $callback();
            $this->handled = true;
        }

        return $this;
    }

    /**
     * @param  callable(): void  $callback
     * @return self<TValue, TFailure>
     */
    public function otherwise(callable $callback): self
    {
        if (! $this->handled) {
            $callback();
            $this->handled = true;
        }

        return $this;
    }

    private function failureMatches(UnitEnum $expectedFailure): bool
    {
        return $this->failure() === $expectedFailure;
    }
}
