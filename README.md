# Laravel Strong Migrations

[![Tests](https://github.com/grazulex/laravel-strongmigrations/actions/workflows/tests.yml/badge.svg)](https://github.com/grazulex/laravel-strongmigrations/actions/workflows/tests.yml)
[![Code Quality](https://github.com/grazulex/laravel-strongmigrations/actions/workflows/code-quality.yml/badge.svg)](https://github.com/grazulex/laravel-strongmigrations/actions/workflows/code-quality.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/grazulex/laravel-strongmigrations.svg)](https://packagist.org/packages/grazulex/laravel-strongmigrations)
[![PHP Version](https://img.shields.io/packagist/php-v/grazulex/laravel-strongmigrations.svg)](https://packagist.org/packages/grazulex/laravel-strongmigrations)
[![License](https://img.shields.io/packagist/l/grazulex/laravel-strongmigrations.svg)](https://packagist.org/packages/grazulex/laravel-strongmigrations)

Detect dangerous migrations before they hit production. Inspired by the Rails [strong_migrations](https://github.com/ankane/strong_migrations) gem.

Laravel Strong Migrations analyzes your migration files using AST parsing (nikic/php-parser) and warns you about operations that could cause downtime, data loss, or lock contention on large tables.

## Features

- **16+ built-in rules** covering common dangerous operations
- **Database-aware** — version-specific rules for MySQL, MariaDB, and PostgreSQL
- **Two modes** — `block` (prevents migration) or `warn` (displays warning)
- **AST-based analysis** — no migration execution required
- **CI/CD ready** — JSON and GitHub Actions output formats
- **Customizable** — disable rules, add custom checks, bypass with `safetyAssured`
- **Multilingual** — English and French translations included

## Installation

```bash
composer require grazulex/laravel-strongmigrations
```

Publish the configuration:

```bash
php artisan strong-migrations:install
```

## How It Works

Strong Migrations listens to Laravel's `MigrationStarted` event. When you run `php artisan migrate`, each migration is parsed and checked against the rule engine **before** it executes.

If a dangerous operation is detected:
- In **block** mode (default): an exception is thrown with a safe alternative
- In **warn** mode: a warning is displayed, migration proceeds

## Rules

### Common Rules (All Databases)

| Rule ID | Detects | Severity |
|---------|---------|----------|
| `remove_column` | Dropping a column | High |
| `rename_column` | Renaming a column | High |
| `rename_table` | Renaming a table | High |
| `create_table_force` | `dropIfExists` before `create` | High |
| `backfill_in_migration` | Data + schema changes in same migration | Medium |
| `add_auto_increment` | Adding auto-increment columns | Medium |
| `raw_sql` | Raw SQL statements | Warning |
| `wide_index` | Index with > 3 columns | Low |

### MySQL / MariaDB Rules

| Rule ID | Detects | Severity |
|---------|---------|----------|
| `add_column_with_default` | Adding NOT NULL column with default (< MySQL 8.0.12 / MariaDB 10.3.2) | High |
| `change_column_type` | Changing column type (table rewrite) | High |
| `set_not_null_mysql` | Setting NOT NULL constraint | Medium |

### PostgreSQL Rules

| Rule ID | Detects | Severity |
|---------|---------|----------|
| `add_index_non_concurrent` | Adding index without CONCURRENTLY | High |
| `add_foreign_key` | Adding foreign key (locks both tables) | High |
| `add_check_constraint` | Adding check constraint | Medium |
| `add_unique_constraint` | Adding unique constraint | Medium |
| `json_column` | Using `json` instead of `jsonb` | Low |
| `set_not_null_pgsql` | Setting NOT NULL (full table scan) | Medium |

## Usage

### Automatic Protection

Once installed, Strong Migrations automatically checks all migrations during `php artisan migrate`:

```
$ php artisan migrate

Dangerous operation detected in migration: 2025_01_15_000001_drop_email.php

[remove_column] Removing column `email` that is used by the application
will cause errors until application servers are restarted.

Safe alternative:

Step 1 - Ignore the column in your Eloquent model:
    protected static array $ignoredColumns = ['email'];

Step 2 - Deploy the code.

Step 3 - Create a migration that drops the column, wrapped in:
    StrongMigrations::safetyAssured(function () { ... });
```

### Artisan Commands

#### Check all migrations

```bash
# Text output (default)
php artisan migrate:check

# JSON output (for CI/CD)
php artisan migrate:check --format=json

# GitHub Actions annotations
php artisan migrate:check --format=github

# Custom path
php artisan migrate:check --path=database/migrations
```

#### Analyze a specific migration

```bash
php artisan migrate:analyze 2025_01_15_000001_drop_email.php
```

### Bypassing Checks

When you've verified a migration is safe, wrap it in `safetyAssured`:

```php
use Grazulex\StrongMigrations\StrongMigrations;

return new class extends Migration
{
    public function up(): void
    {
        StrongMigrations::safetyAssured(function () {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('legacy_field');
            });
        });
    }
};
```

### Custom Checks

Add custom rules in a service provider:

```php
use Grazulex\StrongMigrations\StrongMigrations;

StrongMigrations::addCheck(function (Operation $operation, DatabaseContext $context) {
    if ($operation->type === OperationType::AddColumn && $operation->column === 'metadata') {
        StrongMigrations::stop('Use `data` instead of `metadata` for consistency.');
    }
});
```

### Custom Messages

Override the default message for the next violation:

```php
StrongMigrations::setMessage('This migration has been reviewed and approved by the DBA team.');
```

## Configuration

```php
// config/strong-migrations.php
return [
    // Enable/disable the package
    'enabled' => env('STRONG_MIGRATIONS_ENABLED', true),

    // 'block' or 'warn'
    'mode' => env('STRONG_MIGRATIONS_MODE', 'block'),

    // Override auto-detected database driver
    'database_driver' => env('STRONG_MIGRATIONS_DRIVER'),

    // Override auto-detected database version
    'database_version' => env('STRONG_MIGRATIONS_VERSION'),

    // Skip migrations before this timestamp (format: YYYY_MM_DD_HHMMSS)
    'start_after' => null,

    // Disable specific rules by ID
    'disabled_checks' => [
        // 'remove_column',
        // 'raw_sql',
    ],
];
```

## CI/CD Integration

### GitHub Actions

```yaml
- name: Check migrations
  run: php artisan migrate:check --format=github
```

This outputs annotations that appear directly on pull request diffs.

### Generic CI

```yaml
- name: Check migrations
  run: php artisan migrate:check --format=json
```

Returns exit code 1 if any violations are found.

## Supported Databases

| Database | Version | Notes |
|----------|---------|-------|
| MySQL | 5.7+ | Version-aware rules (8.0.12+ allows instant ADD COLUMN) |
| MariaDB | 10.2+ | Version-aware rules (10.3.2+ allows instant ADD COLUMN) |
| PostgreSQL | 12+ | CONCURRENTLY, foreign key, and constraint rules |
| SQLite | Any | Reduced rule set (safe for development) |

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x

## Testing

```bash
composer test
```

## Code Quality

```bash
composer phpstan    # PHPStan level 8
composer pint       # Laravel Pint
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover a security vulnerability, please see [SECURITY.md](SECURITY.md).

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.
