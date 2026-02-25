<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Rules\PostgreSQL;

use Grazulex\StrongMigrations\Contracts\RuleInterface;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;

class SetNotNullRule implements RuleInterface
{
    public function id(): string
    {
        return 'set_not_null_pgsql';
    }

    public function check(Operation $operation, DatabaseContext $context): ?Violation
    {
        if ($operation->type !== OperationType::SetNotNull) {
            return null;
        }

        $column = $operation->column ?? 'unknown';

        return new Violation(
            ruleId: $this->id(),
            severity: Severity::High,
            message: (string) __('strong-migrations::messages.set_not_null_pgsql.message', ['column' => $column]),
            safeAlternative: (string) __('strong-migrations::messages.set_not_null_pgsql.safe_alternative', ['column' => $column]),
            column: $column,
        );
    }

    public function appliesTo(): array
    {
        return ['pgsql'];
    }
}
