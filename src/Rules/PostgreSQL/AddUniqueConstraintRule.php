<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Rules\PostgreSQL;

use Grazulex\StrongMigrations\Contracts\RuleInterface;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;

class AddUniqueConstraintRule implements RuleInterface
{
    public function id(): string
    {
        return 'add_unique_constraint';
    }

    public function check(Operation $operation, DatabaseContext $context): ?Violation
    {
        if ($operation->type !== OperationType::AddUniqueConstraint) {
            return null;
        }

        $column = $operation->column ?? 'unknown';

        return new Violation(
            ruleId: $this->id(),
            severity: Severity::Medium,
            message: (string) __('strong-migrations::messages.add_unique_constraint.message', ['column' => $column]),
            safeAlternative: (string) __('strong-migrations::messages.add_unique_constraint.safe_alternative', ['column' => $column]),
            column: $column,
        );
    }

    public function appliesTo(): array
    {
        return ['pgsql'];
    }
}
