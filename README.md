<picture>
  <source media="(prefers-color-scheme: dark)" srcset="art/header-dark.png">
  <img alt="Logo for essentials" src="art/header-light.png">
</picture>

# Essentials

<p>
    <a href="https://github.com/ivanfuhr/essentials/actions"><img src="https://github.com/ivanfuhr/essentials/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
    <a href="https://packagist.org/packages/ivanfuhr/essentials"><img src="https://img.shields.io/packagist/dt/ivanfuhr/essentials" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/ivanfuhr/essentials"><img src="https://img.shields.io/packagist/v/ivanfuhr/essentials" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/ivanfuhr/essentials"><img src="https://img.shields.io/packagist/l/ivanfuhr/essentials" alt="License"></a>
</p>

Essentials provide **better defaults** for your Laravel applications including strict models, automatically eagerly loaded relationships, immutable dates, and more! 

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
GITHUB_MONOLOG_ENABLED=true
GITHUB_MONOLOG_REPO=your-org/your-repo
GITHUB_MONOLOG_TOKEN=ghp_...
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
- **[laravel-github-monolog](https://github.com/Naoray/laravel-github-monolog)** — Krishan Koenig and [contributors](https://github.com/Naoray/laravel-github-monolog/graphs/contributors)

Thank you to everyone who made the upstream work possible.

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.
