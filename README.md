# Essentials

<p>
    <a href="https://github.com/ivanfuhr/essentials/actions"><img src="https://github.com/ivanfuhr/essentials/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
    <a href="https://packagist.org/packages/ivanfuhr/essentials"><img src="https://img.shields.io/packagist/dt/ivanfuhr/essentials" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/ivanfuhr/essentials"><img src="https://img.shields.io/packagist/v/ivanfuhr/essentials" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/ivanfuhr/essentials"><img src="https://img.shields.io/packagist/l/ivanfuhr/essentials" alt="License"></a>
</p>

Essentials provide **better defaults** for your Laravel applications including strict models, automatically eagerly loaded relationships, immutable dates, a lightweight `Result` type for service outcomes, and more! 

> **Requires [PHP 8.3+](https://php.net/releases/)**, **[Laravel 11+](https://laravel.com/docs/11.x/)**.

> **Note:** This package modifies the default behavior of Laravel. **It is recommended to use it in new projects** or when you are comfortable with the changes it introduces.

## Installation

⚡️ Get started by requiring the package using [Composer](https://getcomposer.org):

```bash
composer require ivanfuhr/essentials
```

## Features

All features are optional and configurable in `config/essentials.php`.

You may publish the configuration file with:

```bash
php artisan vendor:publish --tag=essentials-config
```

## Table of Contents
- [Strict Models](#-strict-models)
- [Auto Eager Loading](#-auto-eager-loading)
- [Optional Unguarded Models](#-optional-unguarded-models)
- [Immutable Dates](#-immutable-dates)
- [Force HTTPS](#-force-https)
- [Safe Console](#-safe-console)
- [Prevent Stray Requests](#-prevent-stray-requests)
- [Fake Sleep](#-fake-sleep)
- [Result](#-result)
- [Artisan Commands](#-artisan-commands)
  - [make:action](#makeaction)
  - [Database Backups](#database-backups)
  - [translations:extract](#translationsextract)
- [GitHub Issue Logger](#-github-issue-logger)
- [Credits](#credits)
- [License](#license)

### ✅ Strict Models

Improves how Eloquent handles undefined attributes, lazy loading, and invalid assignments.

- Accessing a missing attribute throws an error.
- Lazy loading is blocked unless explicitly allowed.
- Setting undefined attributes throws instead of failing silently.

**Why:** Avoids subtle bugs and makes model behavior easier to reason about.

---

### ⚡️ Auto Eager Loading

Automatically eager loads relationships defined in the model's `$with` property.

**Why:** Reduces N+1 query issues and improves performance without needing `with()` everywhere.

---

### 🔓 Optional Unguarded Models

Disables Laravel's mass assignment protection globally (opt-in).

**Why:** Useful in trusted or local environments where you want to skip defining `$fillable`.

---

### 🕒 Immutable Dates

Uses `CarbonImmutable` instead of mutable date objects across your app.

**Why:** Prevents unexpected date mutations and improves predictability.

---

### 🔒 Force HTTPS

Forces all generated URLs to use `https://`.

**Why:** Ensures all traffic uses secure connections by default.

---

### 🛑 Safe Console

Blocks potentially destructive Artisan commands in production (e.g., `migrate:fresh`).

**Why:** Prevents accidental data loss and adds a safety net in sensitive environments.

---

### 🔄 Prevent Stray Requests

Configures Laravel Http Facade to prevent stray requests.

**Why:** Ensure every HTTP calls during tests have been explicitly faked.

---

### 😴 Fake Sleep

Configures Laravel Sleep Facade to be faked.

**Why:** Avoid unexpected sleep during testing cases.

---

### 📦 Result

A small, framework-agnostic **success/failure** wrapper for service and action outcomes. Expected business failures are modeled as **PHP enums**, not thrown exceptions — so invalid email, duplicate records, and similar cases stay explicit and easy to branch on.

**Namespace:** `IvanFuhr\Essentials\Result\Result`

No configuration or service provider setup is required. Composer autoloads the class and registers global helpers immediately (no service provider).

#### Defining failure enums

```php
enum CreateUserError
{
    case InvalidEmail;
    case EmailAlreadyExists;
}
```

Use a dedicated enum per use case (or domain area). Backed enums (`string` or `int`) work well when you need stable codes for APIs or translations.

#### Creating results

Global helpers are available as soon as the package is installed:

```php
// Success with a value (any type)
$result = success($user);

// Failure with an enum case
$result = fail(CreateUserError::InvalidEmail);
```

You can also use the class directly:

```php
use IvanFuhr\Essentials\Result\Result;

$result = Result::success($user);
$result = Result::fail(CreateUserError::InvalidEmail);
```

> **Note:** The `fail()` helper and `Result::fail()` factory exist because PHP does not allow a static `failure()` factory and an instance `failure()` getter on the same class. The getter returns the enum case: `$result->failure()`.

#### Checking state

| Method | Description |
|--------|-------------|
| `successful()` | `true` when the result holds a success value |
| `failed()` | `true` when the result holds a failure enum |
| `value()` | Returns the success value (call only when `successful()` is `true`) |
| `valueOr($default)` | Returns the success value, or `$default` on failure |
| `failure()` | Returns the failure `UnitEnum` (call only when `failed()` is `true`) |

Prefer `whenSuccessful()` / `whenFailed()` for branching. Use `value()` and `failure()` after checking state, or `valueOr()` when a default is enough.

```php
if ($result->successful()) {
    $user = $result->value();
}

$guest = $result->valueOr(null);

if ($result->failed()) {
    $error = $result->failure(); // CreateUserError enum case
}
```

#### Fluent handling

Chain handlers on the same result. Only the **first matching** handler runs; later handlers are skipped until you start a new chain.

| Method | Description |
|--------|-------------|
| `whenSuccessful(callable $callback)` | Runs only on success; receives the value |
| `whenFailed(UnitEnum $expectedFailure, callable $callback)` | Runs only when the failure enum equals `$expectedFailure` (`===`) |
| `otherwise(callable $callback)` | Runs only if no earlier handler matched |

Typical usage in a controller or action:

```php
enum CreateUserError
{
    case InvalidEmail;
    case EmailAlreadyExists;
}

return $this->createUser->handle($email)
    ->whenSuccessful(fn (User $user) => redirect()->route('users.show', $user))
    ->whenFailed(CreateUserError::InvalidEmail, fn () => back()->withErrors(['email' => 'Invalid email.']))
    ->whenFailed(CreateUserError::EmailAlreadyExists, fn () => back()->withErrors(['email' => 'Email already in use.']))
    ->otherwise(fn () => abort(500));
```

Service returning a `Result`:

```php
final readonly class CreateUser
{
    public function handle(string $email): Result
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return fail(CreateUserError::InvalidEmail);
        }

        if (User::query()->where('email', $email)->exists()) {
            return fail(CreateUserError::EmailAlreadyExists);
        }

        return success(User::create(['email' => $email]));
    }
}
```

**Why:** Keeps happy paths and expected failures explicit, improves testability, and avoids `try/catch` for business rules that are not exceptional.

**Tips:**

- `Result::fail()` only accepts `UnitEnum` — define one enum per operation or bounded context.
- `whenFailed()` compares enum cases with `===`; pass the same case you used in `Result::fail()`.
- Use `otherwise()` for unhandled enum cases (e.g. a new case you have not mapped yet).
- Handlers are for side effects (redirects, logging, mapping to HTTP). Use `value()` / `failure()` when you need the payload after checking `successful()` / `failed()`.

---

### 🏗️ Artisan Commands

#### `make:action`

Quickly generates action classes in your Laravel application:

```bash
php artisan make:action CreateUserAction
```

This creates a clean action class at `app/Actions/CreateUserAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions;

final readonly class CreateUserAction
{
    /**
     * Execute the action.
     */
    public function handle(): void
    {
        DB::transaction(function (): void {
            //
        });
    }
}
```

Actions help organize business logic in dedicated classes, promoting single responsibility and cleaner controllers.

#### Database Backups

PostgreSQL backup, restore, and retention commands powered by `pg_dump` and `pg_restore`.

```bash
php artisan db:backup
php artisan db:restore
php artisan db:backups:prune
```

Configure disk, directory, retention, and binary paths under `backup` in `config/essentials.php`.

#### `translations:extract`

Scans PHP and Blade files for translation strings and generates or updates a JSON language file:

```bash
php artisan translations:extract
php artisan translations:extract app/Filament en
```

Supports `__()`, `trans()`, `trans_choice()`, `@lang()`, and common attribute patterns like `#[Title(...)]` and `#[Validate(as: ...)]`.

## Configuration

All features are configurable through the `essentials.php` config file. By default, most features are enabled, but you can disable any feature by setting its configuration value to `false`:

```php
// config/essentials.php
return [
    'configurables' => [
        IvanFuhr\Essentials\Configurables\ShouldBeStrict::class => true,
        IvanFuhr\Essentials\Configurables\Unguard::class => false,
    ],

    'backup' => [
        'disk' => env('DB_BACKUP_DISK'),
        'directory' => env('DB_BACKUP_DIRECTORY', 'backups'),
        'retention_days' => (int) env('DB_BACKUP_RETENTION_DAYS', 30),
        'pg_dump_binary' => env('PG_DUMP_PATH', 'pg_dump'),
        'pg_restore_binary' => env('PG_RESTORE_PATH', 'pg_restore'),
    ],
];
```

You may also publish the stubs used by this package:

```bash
php artisan vendor:publish --tag=essentials-stubs
```

### 🐙 GitHub Issue Logger

Turn Laravel errors and logs into GitHub issues (with deduplication, comments on repeats, tracing, and customizable Markdown templates). Implemented in `src/Loggers/Github/`.

Publish and configure:

Configure in `config/essentials.php` under `loggers.github` (publish with `php artisan vendor:publish --tag=essentials-config` if needed).

```env
GITHUB_LOGGER_ENABLED=true
GITHUB_LOGGER_REPO=your-org/your-repo
GITHUB_LOGGER_TOKEN=ghp_...
```

When enabled, Essentials registers the `github` log channel automatically. Use it as `LOG_CHANNEL`, in a stack, or explicitly:

```php
Log::channel('github')->error('Something went wrong!');
```

Optional: publish issue templates with `php artisan vendor:publish --tag=essentials-loggers-github-views`.

## Roadmap

- Better defaults before each test case
- General cleanup of the skeleton
- Additional configurables for common Laravel patterns

## Credits

This package is maintained by **[Ivan Führ](https://github.com/ivanfuhr)**.

It builds on ideas and code from these projects:

- **[nunomaduro/essentials](https://github.com/nunomaduro/essentials)** — [Nuno Maduro](https://github.com/nunomaduro)
- **[GitHub issues logger for Laravel](https://github.com/Naoray/laravel-github-monolog)** — Krishan Koenig and [contributors](https://github.com/Naoray/laravel-github-monolog/graphs/contributors)

Thank you to everyone who made the upstream work possible.

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.
