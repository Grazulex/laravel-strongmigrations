<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Rules\PostgreSQL;

use Grazulex\StrongMigrations\Contracts\RuleInterface;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;

class AddForeignKeyRule implements RuleInterface
{
    public function id(): string
    {
        return 'add_foreign_key';
    }

    public function check(Operation $operation, DatabaseContext $context): ?Violation
    {
        if ($operation->type !== OperationType::AddForeignKey) {
            return null;
        }

        // A foreign key declared inside Schema::create has no existing rows
        // to validate, so NOT VALID + VALIDATE CONSTRAINT brings nothing.
        if (($operation->details['new_table'] ?? false) === true) {
            return null;
        }

        $column = $operation->column ?? 'unknown';

        return new Violation(
            ruleId: $this->id(),
            severity: Severity::Medium,
            message: (string) __('strong-migrations::messages.add_foreign_key.message', ['column' => $column]),
            safeAlternative: (string) __('strong-migrations::messages.add_foreign_key.safe_alternative', ['column' => $column]),
            column: $column,
        );
    }

    public function appliesTo(): array
    {
        return ['pgsql'];
    }
}
