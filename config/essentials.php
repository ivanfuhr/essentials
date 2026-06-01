<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Configurables
    |--------------------------------------------------------------------------
    |
    | Each configurable applies a better default to your Laravel application.
    | Disable any feature by setting its value to false.
    |
    */

    'configurables' => [

        /*
        |--------------------------------------------------------------------------
        | Automatically Eager Load Relationships
        |--------------------------------------------------------------------------
        |
        | Note: This option is only available in Laravel 12.8 and above.
        |
        */

        IvanFuhr\Essentials\Configurables\AutomaticallyEagerLoadRelationships::class => true,

        IvanFuhr\Essentials\Configurables\FakeSleep::class => true,

        IvanFuhr\Essentials\Configurables\ForceScheme::class => true,

        IvanFuhr\Essentials\Configurables\ImmutableDates::class => true,

        IvanFuhr\Essentials\Configurables\PreventStrayRequests::class => true,

        IvanFuhr\Essentials\Configurables\ProhibitDestructiveCommands::class => true,

        IvanFuhr\Essentials\Configurables\ShouldBeStrict::class => true,

        IvanFuhr\Essentials\Configurables\Unguard::class => false,

        /*
        |--------------------------------------------------------------------------
        | Per-environment overrides
        |--------------------------------------------------------------------------
        |
        | Specify environments for which each configurable should be active.
        |
        */

        'environments' => [
            IvanFuhr\Essentials\Configurables\ForceScheme::class => ['production'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Backups
    |--------------------------------------------------------------------------
    |
    | Configure PostgreSQL backup, restore, and retention commands.
    |
    */

    'backup' => [
        'disk' => env('DB_BACKUP_DISK'),
        'directory' => env('DB_BACKUP_DIRECTORY', 'backups'),
        'retention_days' => (int) env('DB_BACKUP_RETENTION_DAYS', 30),
        'pg_dump_binary' => env('PG_DUMP_PATH', 'pg_dump'),
        'pg_restore_binary' => env('PG_RESTORE_PATH', 'pg_restore'),
    ],

    /*
    |--------------------------------------------------------------------------
    | GitHub Issue Logger
    |--------------------------------------------------------------------------
    |
    | Log errors as GitHub issues (src/Loggers/Github/). When enabled, registers
    | the `github` log channel automatically when repo and token are set.
    |
    */

    'loggers' => [
        'github' => [
            'enabled' => env('GITHUB_LOGGER_ENABLED', false),
            'repo' => env('GITHUB_LOGGER_REPO'),
            'token' => env('GITHUB_LOGGER_TOKEN'),
            'labels' => [],
            'level' => env('GITHUB_LOGGER_LEVEL', 'error'),
            'deduplication' => [
                'store' => null,
                'prefix' => 'essentials-github:',
                'time' => 3600,
                'track_occurrences' => true,
            ],
            'buffer' => [
                'limit' => 0,
                'flush_on_overflow' => true,
            ],
            'signature_generator' => IvanFuhr\Essentials\Loggers\Github\Deduplication\DefaultSignatureGenerator::class,
            'tracing' => [
                'enabled' => true,
                'environment' => true,
                'user' => true,
                'route' => true,
                'requests' => true,
                'session' => true,
                'queries' => true,
                'jobs' => true,
                'commands' => true,
                'outgoing_requests' => true,
                'livewire' => true,
                'inertia' => true,
                'git' => true,
                'breadcrumbs' => true,
                'query_limit' => 50,
                'breadcrumb_limit' => 40,
                'outgoing_request_limit' => 20,
                'redact' => [
                    'headers' => [
                        'authorization',
                        'proxy-authorization',
                        'cookie',
                        'x-xsrf-token',
                    ],
                    'payload_fields' => [
                        'password',
                        'password_confirmation',
                        '_token',
                        'token',
                        'secret',
                        'api_key',
                        'api_secret',
                    ],
                    'query_bindings' => [
                        'password',
                        'secret',
                        'token',
                    ],
                ],
            ],
        ],
    ],

];
