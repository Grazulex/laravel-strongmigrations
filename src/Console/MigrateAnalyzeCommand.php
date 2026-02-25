<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Console;

use Grazulex\StrongMigrations\Analyzer\MigrationAnalyzer;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Rules\RuleEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;

class MigrateAnalyzeCommand extends Command
{
    protected $signature = 'migrate:analyze
        {migration : Migration file name or path}';

    protected $description = 'Analyze a specific migration for dangerous operations';

    public function handle(MigrationAnalyzer $analyzer, RuleEngine $ruleEngine): int
    {
        /** @var string $migration */
        $migration = $this->argument('migration');
        $filePath = $this->resolveMigrationPath($migration);

        if ($filePath === null) {
            $this->error("Migration not found: {$migration}");

            return self::FAILURE;
        }

        $this->info('Analyzing: '.basename($filePath));
        $this->newLine();

        $operations = $analyzer->analyze($filePath);

        if ($operations === []) {
            $this->info('No operations detected.');

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Operations detected', (string) count($operations));

        foreach ($operations as $operation) {
            $details = $operation->column ?? $operation->table ?? '';
            $this->components->twoColumnDetail(
                "  {$operation->type->value}",
                $details,
            );
        }

        $this->newLine();

        $context = $this->buildDatabaseContext();
        $violations = $ruleEngine->check($operations, $context);

        if ($violations === []) {
            $this->components->info('No violations found.');

            return self::SUCCESS;
        }

        $this->components->error(count($violations).' violation(s) found:');
        $this->newLine();

        foreach ($violations as $violation) {
            $severityLabel = $this->severityLabel($violation->severity);
            $this->line("  {$severityLabel} <fg=white;options=bold>[{$violation->ruleId}]</>");
            $this->line("    {$violation->message}");

            if ($violation->safeAlternative !== null) {
                $this->line("    <fg=green>Safe alternative:</> {$violation->safeAlternative}");
            }

            $this->newLine();
        }

        return self::FAILURE;
    }

    private function resolveMigrationPath(string $migration): ?string
    {
        if (is_file($migration)) {
            return realpath($migration) ?: null;
        }

        $migrationsPath = database_path('migrations');

        if (is_file($migrationsPath.'/'.$migration)) {
            return realpath($migrationsPath.'/'.$migration) ?: null;
        }

        $finder = Finder::create()
            ->files()
            ->name("*{$migration}*")
            ->in($migrationsPath)
            ->sortByName();

        foreach ($finder as $file) {
            return $file->getRealPath();
        }

        return null;
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

    private function severityLabel(Severity $severity): string
    {
        return match ($severity) {
            Severity::High => '<fg=red;options=bold>HIGH</>',
            Severity::Medium => '<fg=yellow;options=bold>MEDIUM</>',
            Severity::Low => '<fg=blue>LOW</>',
            Severity::Warning => '<fg=gray>WARNING</>',
        };
    }
}
