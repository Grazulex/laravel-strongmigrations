<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Rules\Common;

use Grazulex\StrongMigrations\Contracts\RuleInterface;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;

class BackfillInMigrationRule implements RuleInterface
{
    public function id(): string
    {
        return 'backfill_in_migration';
    }

    public function check(Operation $operation, DatabaseContext $context): ?Violation
    {
        if ($operation->type !== OperationType::Backfill) {
            return null;
        }

        return new Violation(
            ruleId: $this->id(),
            severity: Severity::High,
            message: (string) __('strong-migrations::messages.backfill_in_migration.message'),
            safeAlternative: (string) __('strong-migrations::messages.backfill_in_migration.safe_alternative'),
        );
    }

    public function appliesTo(): array
    {
        return ['*'];
    }
}
