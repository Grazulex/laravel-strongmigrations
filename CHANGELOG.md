# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed

- **ci:** `actions/checkout` bumped to v5 in all workflows; `softprops/action-gh-release` bumped to v2 in the release workflow
- `phpunit.xml` now references the PHPUnit 12.5 schema (was 10.1)
- `rector.php` fixed for Rector 2.x (the removed `strictBooleans` prepared set made the `composer rector` script fail); Rector applied: explicit `array` return type on the `JsonReporter` closure, simplified boolean expression in `OperationExtractor`

## [1.1.0](https://github.com/grazulex/laravel-strongmigrations/releases/tag/v1.1.0) (2026-09-17)

### Added

- Laravel 13 support (`illuminate/*` `^12.0|^13.0`)

### Changed

- PHP 8.3 is now the minimum supported version
- Dev dependencies updated: Orchestra Testbench `^10.0|^11.0`, Pest `^3.8|^4.0`, Pest Laravel plugin `^3.2|^4.0`
- **ci:** test matrix now covers PHP 8.3/8.4 x Laravel 12/13 (prefer-lowest and prefer-stable); the manual release workflow runs against Laravel 13
- PHPStan ignore pattern adapted to the `__()` return type change in Laravel 13 (`array|string` instead of `array|string|null`)

### Removed

- Laravel 11 support (end of life)

## [1.0.1](https://github.com/grazulex/laravel-strongmigrations/releases/tag/v1.0.1) (2026-09-17)

### Bug Fixes

- `safetyAssured()` now suppresses `backfill_in_migration`, and read-only `Schema` introspection (`hasTable`, `hasColumn`, ...) no longer counts as a schema mutation ([959142d](https://github.com/grazulex/laravel-strongmigrations/commit/959142d5a2e9f87424c5ccd8768d773fd20eb244), [#1](https://github.com/Grazulex/laravel-strongmigrations/pull/1)) — thanks @bp-behrooz
- Methods chained on `Schema::connection()` are analyzed correctly; foreign key constraint toggles, builder settings and more read-only methods are no longer treated as schema mutations ([1e8f546](https://github.com/grazulex/laravel-strongmigrations/commit/1e8f546f9fd94051931ebf8a68e6a344b46cc4b4), [#2](https://github.com/Grazulex/laravel-strongmigrations/pull/2))
- **ci:** drop Laravel 11 (security EOL) from the test matrix ([1e8f546](https://github.com/grazulex/laravel-strongmigrations/commit/1e8f546f9fd94051931ebf8a68e6a344b46cc4b4))

## [1.0.0](https://github.com/grazulex/laravel-strongmigrations/releases/tag/v1.0.0) (2026-02-25)

### Features

- initial release of laravel-strongmigrations ([74c8d68](https://github.com/grazulex/laravel-strongmigrations/commit/74c8d687f188545f1f983759f6b22c2c7c153868))

### Bug Fixes

- **ci:** remove Feature testsuite from phpunit.xml ([cf7b92c](https://github.com/grazulex/laravel-strongmigrations/commit/cf7b92c10d64ef1732ba60013082aa1e8dd06469))

### Added

- AST-based migration analysis using nikic/php-parser
- 16+ built-in rules for dangerous migration detection
- Common rules: remove_column, rename_column, rename_table, create_table_force, backfill_in_migration, add_auto_increment, raw_sql, wide_index
- MySQL rules: add_column_with_default (version-aware), change_column_type, set_not_null_mysql
- PostgreSQL rules: add_index_non_concurrent, add_foreign_key, add_check_constraint, add_unique_constraint, json_column, set_not_null_pgsql
- Two modes: block (default) and warn
- `StrongMigrations::safetyAssured()` bypass mechanism
- Custom checks via `StrongMigrations::addCheck()`
- Custom messages via `StrongMigrations::setMessage()`
- Artisan commands: `migrate:check`, `migrate:analyze`, `strong-migrations:install`
- Output formats: text (console), JSON, GitHub Actions annotations
- Database version-aware rules (MySQL 8.0.12+, MariaDB 10.3.2+)
- `start_after` configuration to skip legacy migrations
- `disabled_checks` configuration to disable specific rules
- English and French translations
- PHPStan level 8 compliance
- Laravel Pint code style
- GitHub Actions CI (tests on PHP 8.3/8.4, Laravel 11/12)
