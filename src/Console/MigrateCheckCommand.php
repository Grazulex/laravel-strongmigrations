<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Console;

use Grazulex\StrongMigrations\Analyzer\MigrationAnalyzer;
use Grazulex\StrongMigrations\Contracts\ReporterInterface;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Reporters\ConsoleReporter;
use Grazulex\StrongMigrations\Reporters\GithubActionsReporter;
use Grazulex\StrongMigrations\Reporters\JsonReporter;
use Grazulex\StrongMigrations\Rules\RuleEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;

class MigrateCheckCommand extends Command
{
    protected $signature = 'migrate:check
        {--format=text : Output format (text, json, github)}
        {--path= : Path to migrations directory}';

    protected $description = 'Check pending migrations for dangerous operations';

    public function handle(MigrationAnalyzer $analyzer, RuleEngine $ruleEngine): int
    {
        /** @var string $path */
        $path = $this->option('path') ?? database_path('migrations');

        if (! is_dir($path)) {
            $this->error("Migrations directory not found: {$path}");

            return self::FAILURE;
        }

        $context = $this->buildDatabaseContext();
        $reporter = $this->resolveReporter();

        $files = $this->getMigrationFiles($path);

        if ($files === []) {
            $this->info('No migration files found.');

            return self::SUCCESS;
        }

        $hasViolations = false;

        foreach ($files as $file) {
            $operations = $analyzer->analyze($file);

            if ($operations === []) {
                continue;
            }

            $violations = $ruleEngine->check($operations, $context);

            if ($violations === []) {
                continue;
            }

            $hasViolations = true;
            $output = $reporter->report($violations, basename($file));
            $this->line($output);
        }

        if (! $hasViolations) {
            $this->info('All migrations passed checks.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    private function buildDatabaseContext(): DatabaseContext
    {
        $driver = config('strong-migrations.database_driver');
        $version = config('strong-migrations.database_version');

        if ($driver === null) {
            $driver = DB::connection()->getDriverName();
        }

        return new DatabaseContext(
            driver: $driver,
            version: $version,
        );
    }

    private function resolveReporter(): ReporterInterface
    {
        $format = $this->option('format');

        return match ($format) {
            'json' => new JsonReporter,
            'github' => new GithubActionsReporter,
            default => new ConsoleReporter,
        };
    }

    /**
     * @return array<string>
     */
    private function getMigrationFiles(string $path): array
    {
        $finder = Finder::create()
            ->files()
            ->name('*.php')
            ->in($path)
            ->sortByName();

        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }
}
