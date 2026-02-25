<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Data;

final readonly class Violation
{
    public function __construct(
        public string $ruleId,
        public Severity $severity,
        public string $message,
        public ?string $safeAlternative = null,
        public ?string $table = null,
        public ?string $column = null,
    ) {}
}
