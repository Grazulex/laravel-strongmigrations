<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'strong-migrations:install';

    protected $description = 'Install the Strong Migrations configuration';

    public function handle(): int
    {
        $this->call('vendor:publish', [
            '--tag' => 'strong-migrations-config',
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'strong-migrations-lang',
        ]);

        $this->components->info('Strong Migrations installed successfully.');

        return self::SUCCESS;
    }
}
