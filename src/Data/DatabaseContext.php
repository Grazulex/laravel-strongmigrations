<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Data;

final readonly class DatabaseContext
{
    public function __construct(
        public string $driver = 'mysql',
        public ?string $version = null,
    ) {}

    public function isMysql(): bool
    {
        return $this->driver === 'mysql';
    }

    public function isPostgres(): bool
    {
        return $this->driver === 'pgsql';
    }

    public function isMariaDb(): bool
    {
        return $this->driver === 'mariadb';
    }

    public function isSqlite(): bool
    {
        return $this->driver === 'sqlite';
    }

    public function isVersionAtLeast(string $minVersion): bool
    {
        if ($this->version === null) {
            return false;
        }

        return version_compare($this->version, $minVersion, '>=');
    }
}
