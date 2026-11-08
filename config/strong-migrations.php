<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Enable/Disable the package
    |--------------------------------------------------------------------------
    |
    | Allows completely disabling all checks.
    | Useful for specific test or CI environments.
    |
    */
    'enabled' => env('STRONG_MIGRATIONS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Mode (block or warn)
    |--------------------------------------------------------------------------
    |
    | 'block' : Prevents migration execution (default)
    | 'warn'  : Displays a warning but allows execution
    |
    */
    'mode' => env('STRONG_MIGRATIONS_MODE', 'block'),

    /*
    |--------------------------------------------------------------------------
    | Target database driver
    |--------------------------------------------------------------------------
    |
    | Auto-detected by default from the active Laravel connection.
    | Can be forced if needed.
    |
    */
    'database_driver' => null, // null = auto-detect

    /*
    |--------------------------------------------------------------------------
    | Target database version
    |--------------------------------------------------------------------------
    |
    | Used to adapt rules (e.g. MySQL 8.0.12+ supports
    | ALGORITHM=INSTANT for certain operations).
    | null = auto-detect via connection query
    |
    */
    'database_version' => null,

    /*
    |--------------------------------------------------------------------------
    | Ignore migrations created before this date
    |--------------------------------------------------------------------------
    |
    | Useful when installing on an existing project to avoid
    | blocking old migrations already in production.
    | Format: migration name timestamp (e.g. '2026_01_01_000000')
    | Applies to the migration listener and to `migrate:check`.
    |
    */
    'start_after' => null,

    /*
    |--------------------------------------------------------------------------
    | Disabled checks
    |--------------------------------------------------------------------------
    |
    | List of rule identifiers to disable globally.
    |
    */
    'disabled_checks' => [
        // 'add_column_with_default',
        // 'remove_column',
    ],

    /*
    |--------------------------------------------------------------------------
    | Table size threshold (in rows)
    |--------------------------------------------------------------------------
    |
    | Some checks only apply to "large" tables.
    | This threshold defines how many rows make a table "large".
    | 0 = always check.
    |
    */
    'table_size_threshold' => 0,

    /*
    |--------------------------------------------------------------------------
    | Recommended timeouts
    |--------------------------------------------------------------------------
    |
    | Session timeouts applied to the migration connection when each
    | migration starts, and restored to their previous values when it ends.
    | PostgreSQL uses `statement_timeout` and `lock_timeout` (integers are
    | milliseconds, strings accept units such as '10s' or '1h').
    | MySQL / MariaDB use `lock_wait_timeout` (seconds).
    | Set a value to null to leave that setting untouched.
    |
    */
    'timeouts' => [
        'statement_timeout' => '1h',   // PostgreSQL
        'lock_timeout' => '10s',        // PostgreSQL
        'lock_wait_timeout' => 10,      // MySQL / MariaDB (seconds)
    ],

    /*
    |--------------------------------------------------------------------------
    | Safe by default
    |--------------------------------------------------------------------------
    |
    | When enabled, some operations are automatically transformed
    | into their safe version (e.g. concurrent index on PostgreSQL).
    |
    */
    'safe_by_default' => false,

    /*
    |--------------------------------------------------------------------------
    | Message locale
    |--------------------------------------------------------------------------
    |
    | null = English by default. Supports Laravel lang files.
    |
    */
    'locale' => null,
];
