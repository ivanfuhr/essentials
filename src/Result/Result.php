<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Result;

use LogicException;
use UnitEnum;

final class Result
{
    private bool $handled = false;

    private function __construct(
        private readonly bool $success,
        private readonly mixed $payload,
    ) {}

    public static function success(mixed $value): self
    {
        return new self(true, $value);
    }

    public static function fail(UnitEnum $failure): self
    {
        return new self(false, $failure);
    }

    public function successful(): bool
    {
        return $this->success;
    }

    public function failed(): bool
    {
        return ! $this->success;
    }

    public function value(): mixed
    {
        if ($this->failed()) {
            throw new LogicException('Cannot get the value from a failed result. Check successful() first or use valueOr().');
        }

        return $this->payload;
    }

    public function valueOr(mixed $default): mixed
    {
        if ($this->successful()) {
            return $this->payload;
        }

        return $default;
    }

    public function failure(): UnitEnum
    {
        if ($this->successful()) {
            throw new LogicException('Cannot get the failure from a successful result. Check failed() first or use whenFailed().');
        }

        /** @var UnitEnum $failure */
        $failure = $this->payload;

        return $failure;
    }

    /**
     * @param  callable(mixed): void  $callback
     */
    public function whenSuccessful(callable $callback): self
    {
        if ($this->successful() && ! $this->handled) {
            $callback($this->value());
            $this->handled = true;
        }

        return $this;
    }

    /**
     * @param  callable(): void  $callback
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
