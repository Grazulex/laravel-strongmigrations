<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Rules\Common;

use Grazulex\StrongMigrations\Contracts\RuleInterface;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;

class RemoveColumnRule implements RuleInterface
{
    public function id(): string
    {
        return 'remove_column';
    }

    public function check(Operation $operation, DatabaseContext $context): ?Violation
    {
        if ($operation->type !== OperationType::RemoveColumn) {
            return null;
        }

        $column = $operation->column ?? 'unknown';

        return new Violation(
            ruleId: $this->id(),
            severity: Severity::High,
            message: (string) __('strong-migrations::messages.remove_column.message', ['column' => $column]),
            safeAlternative: (string) __('strong-migrations::messages.remove_column.safe_alternative', ['column' => $column]),
            table: $operation->table,
            column: $column,
        );
    }

    public function appliesTo(): array
    {
        return ['*'];
    }
}
