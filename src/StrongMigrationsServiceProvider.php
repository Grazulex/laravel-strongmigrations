<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations;

use Grazulex\StrongMigrations\Analyzer\MigrationAnalyzer;
use Grazulex\StrongMigrations\Console\InstallCommand;
use Grazulex\StrongMigrations\Console\MigrateAnalyzeCommand;
use Grazulex\StrongMigrations\Console\MigrateCheckCommand;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Exceptions\DangerousOperationException;
use Grazulex\StrongMigrations\Rules\Common\AddAutoIncrementRule;
use Grazulex\StrongMigrations\Rules\Common\BackfillInMigrationRule;
use Grazulex\StrongMigrations\Rules\Common\CreateTableForceRule;
use Grazulex\StrongMigrations\Rules\Common\RawSqlRule;
use Grazulex\StrongMigrations\Rules\Common\RemoveColumnRule;
use Grazulex\StrongMigrations\Rules\Common\RenameColumnRule;
use Grazulex\StrongMigrations\Rules\Common\RenameTableRule;
use Grazulex\StrongMigrations\Rules\Common\WideIndexRule;
use Grazulex\StrongMigrations\Rules\MySQL\AddColumnWithDefaultRule;
use Grazulex\StrongMigrations\Rules\MySQL\ChangeColumnTypeRule;
use Grazulex\StrongMigrations\Rules\MySQL\SetNotNullRule as MySqlSetNotNullRule;
use Grazulex\StrongMigrations\Rules\PostgreSQL\AddCheckConstraintRule;
use Grazulex\StrongMigrations\Rules\PostgreSQL\AddForeignKeyRule;
use Grazulex\StrongMigrations\Rules\PostgreSQL\AddIndexNonConcurrentRule;
use Grazulex\StrongMigrations\Rules\PostgreSQL\AddUniqueConstraintRule;
use Grazulex\StrongMigrations\Rules\PostgreSQL\JsonColumnRule;
use Grazulex\StrongMigrations\Rules\PostgreSQL\SetNotNullRule as PgsqlSetNotNullRule;
use Grazulex\StrongMigrations\Rules\RuleEngine;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

class StrongMigrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/strong-migrations.php', 'strong-migrations');

        $this->app->singleton(MigrationAnalyzer::class);

        $this->app->singleton(RuleEngine::class, function (): RuleEngine {
            $engine = new RuleEngine;

            // Common rules (all drivers)
            $engine->addRule(new RemoveColumnRule);
            $engine->addRule(new RenameColumnRule);
            $engine->addRule(new RenameTableRule);
            $engine->addRule(new CreateTableForceRule);
            $engine->addRule(new BackfillInMigrationRule);
            $engine->addRule(new AddAutoIncrementRule);
            $engine->addRule(new RawSqlRule);
            $engine->addRule(new WideIndexRule);

            // MySQL / MariaDB rules
            $engine->addRule(new AddColumnWithDefaultRule);
            $engine->addRule(new ChangeColumnTypeRule);
            $engine->addRule(new MySqlSetNotNullRule);

            // PostgreSQL rules
            $engine->addRule(new AddIndexNonConcurrentRule);
            $engine->addRule(new AddForeignKeyRule);
            $engine->addRule(new AddCheckConstraintRule);
            $engine->addRule(new AddUniqueConstraintRule);
            $engine->addRule(new JsonColumnRule);
            $engine->addRule(new PgsqlSetNotNullRule);

            /** @var array<string> $disabledChecks */
            $disabledChecks = config('strong-migrations.disabled_checks', []);
            $engine->setDisabledChecks($disabledChecks);

            return $engine;
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'strong-migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/strong-migrations.php' => config_path('strong-migrations.php'),
            ], 'strong-migrations-config');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/strong-migrations'),
            ], 'strong-migrations-lang');

            $this->commands([
                MigrateCheckCommand::class,
                MigrateAnalyzeCommand::class,
                InstallCommand::class,
            ]);
        }

        if (! config('strong-migrations.enabled', true)) {
            return;
        }

        $this->registerMigrationListener();
    }

    private function registerMigrationListener(): void
    {
        Event::listen(MigrationStarted::class, function (MigrationStarted $event): void {
            if (StrongMigrations::isSafetyAssured()) {
                return;
            }

            $migration = $event->migration;
            $filePath = $this->resolveMigrationFilePath($migration);

            if ($filePath === null) {
                return;
            }

            if ($this->shouldSkipMigration($filePath)) {
                return;
            }

            /** @var MigrationAnalyzer $analyzer */
            $analyzer = $this->app->make(MigrationAnalyzer::class);
            $operations = $analyzer->analyze($filePath);

            if ($operations === []) {
                return;
            }

            $context = $this->buildDatabaseContext();

            /** @var RuleEngine $ruleEngine */
            $ruleEngine = $this->app->make(RuleEngine::class);
            $violations = $ruleEngine->check($operations, $context);

            if ($violations === []) {
                return;
            }

            $migrationName = basename($filePath);
            $mode = config('strong-migrations.mode', 'block');

            if ($mode === 'block') {
                throw DangerousOperationException::fromViolations($violations, $migrationName);
            }

            foreach ($violations as $violation) {
                $this->outputWarning($violation, $migrationName);
            }
        });
    }

    private function resolveMigrationFilePath(object $migration): ?string
    {
        $reflector = new ReflectionClass($migration);
        $filePath = $reflector->getFileName();

        return $filePath !== false ? $filePath : null;
    }

    private function shouldSkipMigration(string $filePath): bool
    {
        $startAfter = config('strong-migrations.start_after');
        if ($startAfter === null) {
            return false;
        }

        $migrationName = basename($filePath, '.php');

        if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})/', $migrationName, $matches)) {
            return $matches[1] <= $startAfter;
        }

        return false;
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

    private function outputWarning(Data\Violation $violation, string $migrationName): void
    {
        $output = new \Symfony\Component\Console\Output\ConsoleOutput;
        $output->writeln(sprintf(
            '<comment>[strong-migrations] WARNING in %s: [%s] %s</comment>',
            $migrationName,
            $violation->ruleId,
            $violation->message,
        ));
    }
}
