<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Rules\Common;

use Grazulex\StrongMigrations\Contracts\RuleInterface;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;

class RenameColumnRule implements RuleInterface
{
    public function id(): string
    {
        return 'rename_column';
    }

    public function check(Operation $operation, DatabaseContext $context): ?Violation
    {
        if ($operation->type !== OperationType::RenameColumn) {
            return null;
        }

        $from = $operation->details['from'] ?? 'unknown';
        $to = $operation->details['to'] ?? 'unknown';

        return new Violation(
            ruleId: $this->id(),
            severity: Severity::High,
            message: (string) __('strong-migrations::messages.rename_column.message', ['from' => $from, 'to' => $to]),
            safeAlternative: (string) __('strong-migrations::messages.rename_column.safe_alternative', ['from' => $from, 'to' => $to]),
            table: $operation->table,
            column: $from,
        );
    }

    public function appliesTo(): array
    {
        return ['*'];
    }
}
