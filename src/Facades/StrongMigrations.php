<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed safetyAssured(\Closure $callback)
 * @method static bool isSafetyAssured()
 * @method static void addCheck(\Closure $callback)
 * @method static array<\Closure> getCustomChecks()
 * @method static never stop(string $message)
 * @method static void setMessage(string $message)
 * @method static ?string getCustomMessage()
 * @method static void reset()
 *
 * @see \Grazulex\StrongMigrations\StrongMigrations
 */
class StrongMigrations extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Grazulex\StrongMigrations\StrongMigrations::class;
    }
}
