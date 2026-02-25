<?php

declare(strict_types=1);

namespace Tests;

use Grazulex\StrongMigrations\StrongMigrationsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;

abstract class TestCase extends Orchestra
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('strong-migrations.enabled', true);
        $app['config']->set('strong-migrations.mode', 'block');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [
            StrongMigrationsServiceProvider::class,
        ];
    }
}
