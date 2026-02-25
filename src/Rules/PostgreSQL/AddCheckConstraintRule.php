<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Rules\PostgreSQL;

use Grazulex\StrongMigrations\Contracts\RuleInterface;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;

class AddCheckConstraintRule implements RuleInterface
{
    public function id(): string
    {
        return 'add_check_constraint';
    }

    public function check(Operation $operation, DatabaseContext $context): ?Violation
    {
        if ($operation->type !== OperationType::AddCheckConstraint) {
            return null;
        }

        return new Violation(
            ruleId: $this->id(),
            severity: Severity::Medium,
            message: (string) __('strong-migrations::messages.add_check_constraint.message'),
            safeAlternative: (string) __('strong-migrations::messages.add_check_constraint.safe_alternative'),
        );
    }

    public function appliesTo(): array
    {
        return ['pgsql'];
    }
}
