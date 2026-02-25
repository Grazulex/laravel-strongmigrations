<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Rules\PostgreSQL;

use Grazulex\StrongMigrations\Contracts\RuleInterface;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;

class JsonColumnRule implements RuleInterface
{
    public function id(): string
    {
        return 'json_column';
    }

    public function check(Operation $operation, DatabaseContext $context): ?Violation
    {
        if ($operation->type !== OperationType::AddColumn) {
            return null;
        }

        if (($operation->details['column_type'] ?? null) !== 'json') {
            return null;
        }

        $column = $operation->column ?? 'unknown';

        return new Violation(
            ruleId: $this->id(),
            severity: Severity::Low,
            message: (string) __('strong-migrations::messages.json_column.message', ['column' => $column]),
            safeAlternative: (string) __('strong-migrations::messages.json_column.safe_alternative', ['column' => $column]),
            column: $column,
        );
    }

    public function appliesTo(): array
    {
        return ['pgsql'];
    }
}
