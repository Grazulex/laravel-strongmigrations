<?php

declare(strict_types=1);

use Grazulex\StrongMigrations\StrongMigrationsServiceProvider;
use Orchestra\Testbench\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

uses()->beforeEach(function (): void {
    $this->app->register(StrongMigrationsServiceProvider::class);
})->in('Feature', 'Unit');
