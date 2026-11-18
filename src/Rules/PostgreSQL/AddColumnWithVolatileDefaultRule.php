<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Rules\PostgreSQL;

use Grazulex\StrongMigrations\Contracts\RuleInterface;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;

/**
 * Since PostgreSQL 11, adding a column with a non-volatile default only
 * stores the value in the catalog. A volatile default (gen_random_uuid(),
 * clock_timestamp(), random(), nextval(), ...) must still be computed for
 * every existing row, which rewrites the whole table under an ACCESS
 * EXCLUSIVE lock, whatever the PostgreSQL version.
 */
class AddColumnWithVolatileDefaultRule implements RuleInterface
{
    public function id(): string
    {
        return 'add_column_volatile_default';
    }

    public function check(Operation $operation, DatabaseContext $context): ?Violation
    {
        if ($operation->type !== OperationType::AddColumn) {
            return null;
        }

        if (($operation->details['volatile_default'] ?? false) !== true) {
            return null;
        }

        // A column declared inside Schema::create() belongs to a new, empty
        // table: there is no existing row to rewrite.
        if (($operation->details['new_table'] ?? false) === true) {
            return null;
        }

        $column = $operation->column ?? 'unknown';
        $expression = $operation->details['default_expression'] ?? null;

        return new Violation(
            ruleId: $this->id(),
            severity: Severity::High,
            message: (string) __('strong-migrations::messages.add_column_volatile_default.message', [
                'column' => $column,
                'expression' => is_string($expression) ? $expression : 'volatile expression',
            ]),
            safeAlternative: (string) __('strong-migrations::messages.add_column_volatile_default.safe_alternative', [
                'column' => $column,
                'expression' => is_string($expression) ? $expression : 'gen_random_uuid()',
            ]),
            column: $column,
        );
    }

    public function appliesTo(): array
    {
        return ['pgsql'];
    }
}
