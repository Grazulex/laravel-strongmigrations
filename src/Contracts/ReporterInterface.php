<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Contracts;

use Grazulex\StrongMigrations\Data\Violation;

interface ReporterInterface
{
    /**
     * @param  array<Violation>  $violations
     */
    public function report(array $violations, string $migrationName): string;
}
