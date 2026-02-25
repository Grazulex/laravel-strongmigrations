<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Rules\Common;

use Grazulex\StrongMigrations\Contracts\RuleInterface;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;

class CreateTableForceRule implements RuleInterface
{
    public function id(): string
    {
        return 'create_table_force';
    }

    public function check(Operation $operation, DatabaseContext $context): ?Violation
    {
        if ($operation->type !== OperationType::DropTable) {
            return null;
        }

        $table = $operation->table ?? 'unknown';

        return new Violation(
            ruleId: $this->id(),
            severity: Severity::High,
            message: (string) __('strong-migrations::messages.create_table_force.message', ['table' => $table]),
            safeAlternative: (string) __('strong-migrations::messages.create_table_force.safe_alternative', ['table' => $table]),
            table: $table,
        );
    }

    public function appliesTo(): array
    {
        return ['*'];
    }
}
