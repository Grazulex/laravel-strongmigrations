# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0](https://github.com/grazulex/laravel-strongmigrations/releases/tag/v1.0.0) (2026-02-25)

### Features

- initial release of laravel-strongmigrations ([74c8d68](https://github.com/grazulex/laravel-strongmigrations/commit/74c8d687f188545f1f983759f6b22c2c7c153868))

### Bug Fixes

- **ci:** remove Feature testsuite from phpunit.xml ([cf7b92c](https://github.com/grazulex/laravel-strongmigrations/commit/cf7b92c10d64ef1732ba60013082aa1e8dd06469))
The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
