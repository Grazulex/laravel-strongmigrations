<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Support;

/**
 * Applies the `start_after` baseline: migrations whose timestamp is lower
 * than or equal to the baseline are considered legacy and are not checked.
 */
final class MigrationBaseline
{
    /**
     * Determine whether the given migration file is covered by the baseline.
     *
     * When no baseline is given, the `strong-migrations.start_after`
     * configuration value is used.
     */
    public static function shouldSkip(string $filePath, ?string $startAfter = null): bool
    {
        $startAfter ??= self::configuredStartAfter();

        if ($startAfter === null) {
            return false;
        }

        $migrationName = basename($filePath, '.php');

        if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})/', $migrationName, $matches) === 1) {
            return $matches[1] <= $startAfter;
        }

        return false;
    }

    public static function configuredStartAfter(): ?string
    {
        $startAfter = config('strong-migrations.start_after');

        if (! is_string($startAfter) || mb_trim($startAfter) === '') {
            return null;
        }

        return mb_trim($startAfter);
    }
}
