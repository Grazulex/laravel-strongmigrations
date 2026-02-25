<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Rules\Common;

use Grazulex\StrongMigrations\Contracts\RuleInterface;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;

class RawSqlRule implements RuleInterface
{
    public function id(): string
    {
        return 'raw_sql';
    }

    public function check(Operation $operation, DatabaseContext $context): ?Violation
    {
        if ($operation->type !== OperationType::RawSql) {
            return null;
        }

        return new Violation(
            ruleId: $this->id(),
            severity: Severity::Warning,
            message: (string) __('strong-migrations::messages.raw_sql.message'),
            safeAlternative: (string) __('strong-migrations::messages.raw_sql.safe_alternative'),
        );
    }

    public function appliesTo(): array
    {
        return ['*'];
    }
}
