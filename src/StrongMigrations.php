<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations;

use Closure;
use Grazulex\StrongMigrations\Exceptions\DangerousOperationException;

class StrongMigrations
{
    protected static bool $safetyAssured = false;

    /**
     * @var array<Closure>
     */
    protected static array $customChecks = [];

    protected static ?string $customMessage = null;

    public static function safetyAssured(Closure $callback): mixed
    {
        static::$safetyAssured = true;

        try {
            return $callback();
        } finally {
            static::$safetyAssured = false;
        }
    }

    public static function isSafetyAssured(): bool
    {
        return static::$safetyAssured;
    }

    public static function addCheck(Closure $callback): void
    {
        static::$customChecks[] = $callback;
    }

    /**
     * @return array<Closure>
     */
    public static function getCustomChecks(): array
    {
        return static::$customChecks;
    }

    public static function stop(string $message): never
    {
        throw new DangerousOperationException($message);
    }

    public static function setMessage(string $message): void
    {
        static::$customMessage = $message;
    }

    public static function getCustomMessage(): ?string
    {
        return static::$customMessage;
    }

    public static function resetCustomMessage(): void
    {
        static::$customMessage = null;
    }

    public static function reset(): void
    {
        static::$safetyAssured = false;
        static::$customChecks = [];
        static::$customMessage = null;
    }
}
