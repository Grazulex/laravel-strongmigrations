# Laravel Strong Migrations

## Project Overview

Laravel package that detects dangerous migrations, prevents their execution, and guides developers toward safe alternatives. Inspired by Rails `strong_migrations` gem.

## Technical Stack

- **PHP**: ^8.2
- **Laravel**: 11.x / 12.x
- **Namespace**: `Grazulex\StrongMigrations`
- **Key dependency**: `nikic/php-parser ^5.0` (AST analysis)
- **Testing**: Pest PHP with Orchestra Testbench
- **Static Analysis**: PHPStan level 5, Larastan
- **Code Style**: Laravel Pint

## Architecture

```
src/
├── StrongMigrationsServiceProvider.php  # Entry point, event listener
├── StrongMigrations.php                 # Main class (safetyAssured, addCheck, stop, setMessage)
├── Facades/StrongMigrations.php         # Laravel Facade
├── Data/                                # Value Objects (Severity, OperationType, Operation, DatabaseContext, Violation)
├── Exceptions/                          # DangerousOperationException
├── Analyzer/                            # AST parsing (MigrationAnalyzer, OperationExtractor, BlueprintInterceptor)
├── Rules/                               # 16+ rules (Common/, MySQL/, PostgreSQL/)
├── Console/                             # Artisan commands (migrate:check, migrate:analyze, strong-migrations:install)
└── Reporters/                           # Output formatters (Console, JSON, GithubActions)
```

## Conventions

- Follow turbomaker patterns for tooling configs (pint.json, rector.php, phpstan.neon, phpunit.xml)
- `declare(strict_types=1)` in every PHP file
- Conventional Commits for all git messages
- No AI references in commits
- Config published as `strong-migrations`
- Translations in `lang/en/` and `lang/fr/`

## Key Design Decisions

- **Event-driven**: Listens to `MigrationStarted` event to intercept migrations
- **AST-based analysis**: Uses nikic/php-parser to extract operations from migration files
- **Rule engine**: Each rule implements `RuleInterface` with `id()`, `check()`, `appliesTo()`
- **safetyAssured**: Static flag pattern for bypass, detected in AST via StrongMigrations::safetyAssured() calls
- **Two modes**: `block` (throws exception) and `warn` (displays warning)

## Commands

```bash
composer test          # Run Pest tests
composer pint          # Fix code style
composer phpstan       # Static analysis
composer rector        # Rector fixes
composer full          # All checks
```

## Spec

Full specification: `laravel-strongmigrations-spec.md`
