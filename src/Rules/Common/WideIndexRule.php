<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Rules\Common;

use Grazulex\StrongMigrations\Contracts\RuleInterface;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;

class WideIndexRule implements RuleInterface
{
    private const MAX_COLUMNS = 3;

    public function id(): string
    {
        return 'wide_index';
    }

    public function check(Operation $operation, DatabaseContext $context): ?Violation
    {
        if ($operation->type !== OperationType::AddIndex) {
            return null;
        }

        $columnCount = $operation->details['column_count'] ?? 0;
        if ($columnCount <= self::MAX_COLUMNS) {
            return null;
        }

        return new Violation(
            ruleId: $this->id(),
            severity: Severity::Low,
            message: (string) __('strong-migrations::messages.wide_index.message', ['count' => $columnCount]),
            safeAlternative: (string) __('strong-migrations::messages.wide_index.safe_alternative'),
        );
    }

    public function appliesTo(): array
    {
        return ['*'];
    }
}
