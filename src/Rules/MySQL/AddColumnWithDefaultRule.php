<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Rules\MySQL;

use Grazulex\StrongMigrations\Contracts\RuleInterface;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;

class AddColumnWithDefaultRule implements RuleInterface
{
    public function id(): string
    {
        return 'add_column_with_default';
    }

    public function check(Operation $operation, DatabaseContext $context): ?Violation
    {
        if ($operation->type !== OperationType::AddColumn) {
            return null;
        }

        if (! ($operation->details['has_default_not_null'] ?? false)) {
            return null;
        }

        // MySQL >= 8.0.12 supports ALGORITHM=INSTANT, so this is safe
        if ($context->isMysql() && $context->isVersionAtLeast('8.0.12')) {
            return null;
        }

        // MariaDB >= 10.3.2 supports ALGORITHM=INSTANT for some cases
        if ($context->isMariaDb() && $context->isVersionAtLeast('10.3.2')) {
            return null;
        }

        $column = $operation->column ?? 'unknown';

        return new Violation(
            ruleId: $this->id(),
            severity: Severity::High,
            message: (string) __('strong-migrations::messages.add_column_with_default.message', ['column' => $column]),
            safeAlternative: (string) __('strong-migrations::messages.add_column_with_default.safe_alternative', ['column' => $column]),
            column: $column,
        );
    }

    public function appliesTo(): array
    {
        return ['mysql', 'mariadb'];
    }
}
