<?php

declare(strict_types=1);

use IvanFuhr\Essentials\Result\Result;

enum CreateUserError
{
    case InvalidEmail;
    case EmailAlreadyExists;
}

enum OtherError
{
    case Unexpected;
}

it('exposes global success and fail helpers', function (): void {
    expect(success('ok'))
        ->toBeInstanceOf(Result::class)
        ->and(success('ok')->successful())->toBeTrue()
        ->and(fail(CreateUserError::InvalidEmail)->failed())->toBeTrue()
        ->and(fail(CreateUserError::InvalidEmail)->failure())->toBe(CreateUserError::InvalidEmail);
});

it('creates successful and failed results', function (): void {
    $success = Result::success('ok');
    $failure = Result::fail(CreateUserError::InvalidEmail);

    expect($success->successful())->toBeTrue()
        ->and($success->failed())->toBeFalse()
        ->and($failure->successful())->toBeFalse()
        ->and($failure->failed())->toBeTrue();
});

it('returns the value from a successful result', function (): void {
    $result = Result::success(['id' => 1]);

    expect($result->value())->toBe(['id' => 1]);
});

it('throws a logic exception when reading the value from a failed result', function (): void {
    Result::fail(CreateUserError::InvalidEmail)->value();
})->throws(LogicException::class);

it('returns the value or a default from a failed result', function (): void {
    $result = Result::fail(CreateUserError::InvalidEmail);

    expect($result->valueOr('fallback'))->toBe('fallback');
});

it('returns the value from valueOr on success', function (): void {
    $result = Result::success('user');

    expect($result->valueOr('fallback'))->toBe('user');
});

it('returns the failure enum from a failed result', function (): void {
    $result = Result::fail(CreateUserError::EmailAlreadyExists);

    expect($result->failure())->toBe(CreateUserError::EmailAlreadyExists);
});

it('throws a logic exception when reading the failure from a successful result', function (): void {
    Result::success('ok')->failure();
})->throws(LogicException::class);

it('runs whenSuccessful only for successful results', function (): void {
    $called = false;

    Result::success('user')
        ->whenSuccessful(function (string $value) use (&$called): void {
            $called = true;

            expect($value)->toBe('user');
        });

    expect($called)->toBeTrue();

    $called = false;

    Result::fail(CreateUserError::InvalidEmail)
        ->whenSuccessful(function (): void {
            $called = true;
        });

    expect($called)->toBeFalse();
});

it('runs whenFailed only for matching failures', function (): void {
    $invalidEmail = false;
    $alreadyExists = false;

    Result::fail(CreateUserError::InvalidEmail)
        ->whenFailed(CreateUserError::EmailAlreadyExists, function () use (&$alreadyExists): void {
            $alreadyExists = true;
        })
        ->whenFailed(CreateUserError::InvalidEmail, function () use (&$invalidEmail): void {
            $invalidEmail = true;
        });

    expect($invalidEmail)->toBeTrue()
        ->and($alreadyExists)->toBeFalse();
});

it('runs otherwise only when nothing handled the result', function (): void {
    $otherwise = false;

    Result::fail(CreateUserError::InvalidEmail)
        ->whenFailed(CreateUserError::EmailAlreadyExists, function (): void {})
        ->otherwise(function () use (&$otherwise): void {
            $otherwise = true;
        });

    expect($otherwise)->toBeTrue();
});

it('does not run otherwise after a handler matched', function (): void {
    $otherwise = false;

    Result::success('user')
        ->whenSuccessful(function (): void {})
        ->otherwise(function () use (&$otherwise): void {
            $otherwise = true;
        });

    expect($otherwise)->toBeFalse();
});

it('chains handlers like the expected service usage', function (): void {
    $outcome = null;

    $handle = function (Result $result) use (&$outcome): void {
        $result
            ->whenSuccessful(function (string $user) use (&$outcome): void {
                $outcome = 'created:'.$user;
            })
            ->whenFailed(CreateUserError::InvalidEmail, function () use (&$outcome): void {
                $outcome = 'invalid-email';
            })
            ->whenFailed(CreateUserError::EmailAlreadyExists, function () use (&$outcome): void {
                $outcome = 'email-exists';
            })
            ->otherwise(function () use (&$outcome): void {
                $outcome = 'unknown';
            });
    };

    $handle(Result::success('ivan@example.com'));
    expect($outcome)->toBe('created:ivan@example.com');

    $handle(Result::fail(CreateUserError::InvalidEmail));
    expect($outcome)->toBe('invalid-email');

    $handle(Result::fail(CreateUserError::EmailAlreadyExists));
    expect($outcome)->toBe('email-exists');

    $handle(Result::fail(OtherError::Unexpected));
    expect($outcome)->toBe('unknown');
});
