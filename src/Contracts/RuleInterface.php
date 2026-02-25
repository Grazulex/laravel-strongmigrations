<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Contracts;

use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\Violation;

interface RuleInterface
{
    public function id(): string;

    public function check(Operation $operation, DatabaseContext $context): ?Violation;

    /**
     * @return array<string>
     */
    public function appliesTo(): array;
}
