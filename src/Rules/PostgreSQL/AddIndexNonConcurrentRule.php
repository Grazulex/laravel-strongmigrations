<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Rules\PostgreSQL;

use Grazulex\StrongMigrations\Contracts\RuleInterface;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;

class AddIndexNonConcurrentRule implements RuleInterface
{
    public function id(): string
    {
        return 'add_index_non_concurrent';
    }

    public function check(Operation $operation, DatabaseContext $context): ?Violation
    {
        if ($operation->type !== OperationType::AddIndex) {
            return null;
        }

        return new Violation(
            ruleId: $this->id(),
            severity: Severity::Medium,
            message: (string) __('strong-migrations::messages.add_index_non_concurrent.message'),
            safeAlternative: (string) __('strong-migrations::messages.add_index_non_concurrent.safe_alternative'),
        );
    }

    public function appliesTo(): array
    {
        return ['pgsql'];
    }
}
