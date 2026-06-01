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

];
