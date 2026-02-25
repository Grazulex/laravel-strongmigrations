<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Data;

final readonly class Operation
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public OperationType $type,
        public ?string $table = null,
        public ?string $column = null,
        public array $details = [],
        public bool $insideSafetyAssured = false,
    ) {}
}
