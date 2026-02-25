<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Rules\Common;

use Grazulex\StrongMigrations\Contracts\RuleInterface;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;

class AddAutoIncrementRule implements RuleInterface
{
    public function id(): string
    {
        return 'add_auto_increment';
    }

    public function check(Operation $operation, DatabaseContext $context): ?Violation
    {
        if ($operation->type !== OperationType::AddColumn) {
            return null;
        }

        if (! ($operation->details['auto_increment'] ?? false)) {
            return null;
        }

        // SQLite handles auto-increment fine
        if ($context->isSqlite()) {
            return null;
        }

        $column = $operation->column ?? 'unknown';

        return new Violation(
            ruleId: $this->id(),
            severity: Severity::Medium,
            message: (string) __('strong-migrations::messages.add_auto_increment.message', ['column' => $column]),
            safeAlternative: (string) __('strong-migrations::messages.add_auto_increment.safe_alternative', ['column' => $column]),
            column: $column,
        );
    }

    public function appliesTo(): array
    {
        return ['mysql', 'mariadb', 'pgsql'];
    }
}
