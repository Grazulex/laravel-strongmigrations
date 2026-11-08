<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Support;

use Illuminate\Database\Connection;

/**
 * Applies the configured `timeouts` to the migration connection while a
 * migration runs, then restores the previous session values.
 *
 * - PostgreSQL: `statement_timeout` and `lock_timeout`
 * - MySQL / MariaDB: `lock_wait_timeout` (seconds)
 *
 * Other drivers are left untouched. A `null` value disables a setting.
 */
final class MigrationTimeouts
{
    /**
     * Previous session values, indexed by connection name then setting.
     *
     * @var array<string, array<string, string|null>>
     */
    private array $previous = [];

    public function apply(Connection $connection): void
    {
        $key = $this->key($connection);

        if (array_key_exists($key, $this->previous)) {
            return;
        }

        $driver = $connection->getDriverName();
        $settings = $this->settingsFor($driver);

        if ($settings === []) {
            return;
        }

        $previous = [];

        foreach ($settings as $setting => $value) {
            $previous[$setting] = $this->read($connection, $driver, $setting);
            $this->write($connection, $driver, $setting, $value);
        }

        $this->previous[$key] = $previous;
    }

    public function restore(Connection $connection): void
    {
        $key = $this->key($connection);

        if (! array_key_exists($key, $this->previous)) {
            return;
        }

        $driver = $connection->getDriverName();

        foreach ($this->previous[$key] as $setting => $value) {
            if ($value !== null) {
                $this->write($connection, $driver, $setting, $value);
            }
        }

        unset($this->previous[$key]);
    }

    /**
     * Resolve the settings to apply for the given driver.
     *
     * @return array<string, string>
     */
    public function settingsFor(string $driver): array
    {
        $timeouts = config('strong-migrations.timeouts', []);

        if (! is_array($timeouts)) {
            return [];
        }

        $settings = [];

        if ($driver === 'pgsql') {
            foreach (['statement_timeout', 'lock_timeout'] as $setting) {
                $value = $this->normalizePostgresValue($timeouts[$setting] ?? null);

                if ($value !== null) {
                    $settings[$setting] = $value;
                }
            }
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $value = $this->normalizeSeconds($timeouts['lock_wait_timeout'] ?? null);

            if ($value !== null) {
                $settings['lock_wait_timeout'] = $value;
            }
        }

        return $settings;
    }

    private function normalizePostgresValue(mixed $value): ?string
    {
        if (is_int($value)) {
            return $value >= 0 ? (string) $value : null;
        }

        if (! is_string($value) || mb_trim($value) === '') {
            return null;
        }

        return mb_trim($value);
    }

    private function normalizeSeconds(mixed $value): ?string
    {
        if (is_string($value) && preg_match('/^\s*\d+\s*$/', $value) === 1) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value < 1) {
            return null;
        }

        return (string) $value;
    }

    private function read(Connection $connection, string $driver, string $setting): ?string
    {
        $row = $driver === 'pgsql'
            ? $connection->selectOne('select current_setting(?) as value', [$setting])
            : $connection->selectOne(sprintf('select @@session.%s as value', $setting));

        if (! is_object($row) || ! isset($row->value) || ! is_scalar($row->value)) {
            return null;
        }

        return (string) $row->value;
    }

    private function write(Connection $connection, string $driver, string $setting, string $value): void
    {
        if ($driver === 'pgsql') {
            $connection->selectOne('select set_config(?, ?, false)', [$setting, $value]);

            return;
        }

        $connection->statement(sprintf('set session %s = %d', $setting, (int) $value));
    }

    private function key(Connection $connection): string
    {
        $name = $connection->getName();

        return is_string($name) && $name !== '' ? $name : 'default';
    }
}
