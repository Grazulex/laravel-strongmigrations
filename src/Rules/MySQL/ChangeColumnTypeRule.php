<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Rules\MySQL;

use Grazulex\StrongMigrations\Contracts\RuleInterface;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;

class ChangeColumnTypeRule implements RuleInterface
{
    public function id(): string
    {
        return 'change_column_type';
    }

    public function check(Operation $operation, DatabaseContext $context): ?Violation
    {
        if ($operation->type !== OperationType::ChangeColumn) {
            return null;
        }

        $column = $operation->column ?? 'unknown';

        return new Violation(
            ruleId: $this->id(),
            severity: Severity::High,
            message: (string) __('strong-migrations::messages.change_column_type.message', ['column' => $column]),
            safeAlternative: (string) __('strong-migrations::messages.change_column_type.safe_alternative', ['column' => $column]),
            column: $column,
        );
    }

    public function appliesTo(): array
    {
        return ['mysql', 'mariadb', 'pgsql'];
    }
}
