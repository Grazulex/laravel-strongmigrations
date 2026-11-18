<?php

declare(strict_types=1);

use Grazulex\StrongMigrations\Analyzer\MigrationAnalyzer;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Rules\MySQL\AddColumnWithDefaultRule;
use Grazulex\StrongMigrations\Rules\PostgreSQL\AddColumnWithVolatileDefaultRule;
use Grazulex\StrongMigrations\Rules\RuleEngine;

function volatileDefaultMigration(string $body, string $schemaCall = "Schema::table('users'"): string
{
    return <<<PHP
    <?php
    use Illuminate\\Database\\Migrations\\Migration;
    use Illuminate\\Database\\Query\\Expression;
    use Illuminate\\Database\\Schema\\Blueprint;
    use Illuminate\\Support\\Facades\\DB;
    use Illuminate\\Support\\Facades\\Schema;

    return new class extends Migration {
        public function up(): void
        {
            {$schemaCall}, function (Blueprint \$table) {
                {$body}
            });
        }
    };
    PHP;
}

/**
 * @return array<string>
 */
function volatileDefaultRuleIds(string $code, DatabaseContext $context): array
{
    $engine = new RuleEngine;
    $engine->addRule(new AddColumnWithVolatileDefaultRule);
    $engine->addRule(new AddColumnWithDefaultRule);

    $operations = (new MigrationAnalyzer)->analyzeCode($code);

    return array_map(fn ($violation) => $violation->ruleId, $engine->check($operations, $context));
}

// Rule unit tests

it('pgsql: flags an added column with a volatile default', function (): void {
    $operation = new Operation(
        type: OperationType::AddColumn,
        column: 'public_id',
        details: ['volatile_default' => true, 'default_expression' => 'gen_random_uuid()'],
    );

    $violation = (new AddColumnWithVolatileDefaultRule)->check($operation, new DatabaseContext('pgsql', '16.2'));

    expect($violation)->not->toBeNull();
    expect($violation->ruleId)->toBe('add_column_volatile_default');
    expect($violation->severity)->toBe(Severity::High);
    expect($violation->column)->toBe('public_id');
    expect($violation->message)->toContain('gen_random_uuid()');
    expect($violation->safeAlternative)->toContain('SET DEFAULT gen_random_uuid()');
});

it('pgsql: ignores an added column with a non-volatile default', function (): void {
    $operation = new Operation(
        type: OperationType::AddColumn,
        column: 'status',
        details: ['has_default_not_null' => true],
    );

    expect((new AddColumnWithVolatileDefaultRule)->check($operation, new DatabaseContext('pgsql')))->toBeNull();
});

it('pgsql: ignores a volatile default on a new table', function (): void {
    $operation = new Operation(
        type: OperationType::AddColumn,
        column: 'public_id',
        details: ['volatile_default' => true, 'default_expression' => 'gen_random_uuid()', 'new_table' => true],
    );

    expect((new AddColumnWithVolatileDefaultRule)->check($operation, new DatabaseContext('pgsql')))->toBeNull();
});

it('pgsql: ignores other operation types', function (): void {
    $operation = new Operation(
        type: OperationType::ChangeColumn,
        column: 'public_id',
        details: ['volatile_default' => true],
    );

    expect((new AddColumnWithVolatileDefaultRule)->check($operation, new DatabaseContext('pgsql')))->toBeNull();
});

it('applies only to pgsql', function (): void {
    expect((new AddColumnWithVolatileDefaultRule)->appliesTo())->toBe(['pgsql']);
});

// Extractor tests

it('extracts the volatile default from DB::raw()', function (): void {
    $code = volatileDefaultMigration("\$table->uuid('public_id')->default(DB::raw('gen_random_uuid()'));");

    $operations = array_values(array_filter(
        (new MigrationAnalyzer)->analyzeCode($code),
        fn (Operation $op): bool => $op->type === OperationType::AddColumn,
    ));

    expect($operations)->toHaveCount(1);
    expect($operations[0]->column)->toBe('public_id');
    expect($operations[0]->details['default_expression'])->toBe('gen_random_uuid()');
    expect($operations[0]->details['volatile_default'])->toBeTrue();
    expect($operations[0]->details['has_default_not_null'])->toBeTrue();
});

it('extracts a volatile default on a nullable column from new Expression()', function (): void {
    $code = volatileDefaultMigration("\$table->timestamp('seen_at')->nullable()->default(new Expression('clock_timestamp()'));");

    $operations = array_values(array_filter(
        (new MigrationAnalyzer)->analyzeCode($code),
        fn (Operation $op): bool => $op->type === OperationType::AddColumn,
    ));

    expect($operations)->toHaveCount(1);
    expect($operations[0]->details['volatile_default'])->toBeTrue();
    expect($operations[0]->details)->not->toHaveKey('has_default_not_null');
});

it('does not mark a stable raw default as volatile', function (): void {
    $code = volatileDefaultMigration("\$table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));");

    $operations = array_values(array_filter(
        (new MigrationAnalyzer)->analyzeCode($code),
        fn (Operation $op): bool => $op->type === OperationType::AddColumn,
    ));

    expect($operations)->toHaveCount(1);
    expect($operations[0]->details['default_expression'])->toBe('CURRENT_TIMESTAMP');
    expect($operations[0]->details)->not->toHaveKey('volatile_default');
});

it('does not emit an operation for a nullable column with a stable default', function (): void {
    $code = volatileDefaultMigration("\$table->string('status')->nullable()->default(DB::raw('now()'));");

    $operations = array_filter(
        (new MigrationAnalyzer)->analyzeCode($code),
        fn (Operation $op): bool => $op->type === OperationType::AddColumn,
    );

    expect($operations)->toBeEmpty();
});

// End-to-end

it('pgsql: reports volatile defaults added to an existing table', function (string $body): void {
    expect(volatileDefaultRuleIds(volatileDefaultMigration($body), new DatabaseContext('pgsql', '16.0')))
        ->toBe(['add_column_volatile_default']);
})->with([
    'gen_random_uuid' => ["\$table->uuid('public_id')->default(DB::raw('gen_random_uuid()'));"],
    'uuid_generate_v4' => ["\$table->uuid('public_id')->default(DB::raw('uuid_generate_v4()'));"],
    'clock_timestamp' => ["\$table->timestamp('seen_at')->default(new Expression('clock_timestamp()'));"],
    'nullable random' => ["\$table->float('weight')->nullable()->default(DB::raw('RANDOM()'));"],
    'fully qualified' => ["\$table->uuid('public_id')->default(new \\Illuminate\\Database\\Query\\Expression('gen_random_uuid()'));"],
]);

it('pgsql: does not report constant, stable or literal defaults', function (string $body): void {
    expect(volatileDefaultRuleIds(volatileDefaultMigration($body), new DatabaseContext('pgsql', '16.0')))
        ->toBeEmpty();
})->with([
    'string literal' => ["\$table->string('status')->default('active');"],
    'function name as literal' => ["\$table->string('kind')->default('gen_random_uuid()');"],
    'now()' => ["\$table->timestamp('created_at')->default(DB::raw('now()'));"],
    'useCurrent' => ["\$table->timestamp('created_at')->useCurrent();"],
]);

it('pgsql: does not report volatile defaults inside Schema::create', function (): void {
    $code = volatileDefaultMigration(
        "\$table->uuid('public_id')->default(DB::raw('gen_random_uuid()'));",
        "Schema::create('users'",
    );

    expect(volatileDefaultRuleIds($code, new DatabaseContext('pgsql', '16.0')))->toBeEmpty();
});

it('does not report volatile defaults on mysql', function (): void {
    $code = volatileDefaultMigration("\$table->uuid('public_id')->default(DB::raw('gen_random_uuid()'));");

    expect(volatileDefaultRuleIds($code, new DatabaseContext('mysql', '8.0.30')))->toBeEmpty();
});

it('respects safetyAssured for volatile defaults', function (): void {
    $code = <<<'PHP'
    <?php
    use Grazulex\StrongMigrations\StrongMigrations;
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            StrongMigrations::safetyAssured(function () {
                Schema::table('users', function (Blueprint $table) {
                    $table->uuid('public_id')->default(DB::raw('gen_random_uuid()'));
                });
            });
        }
    };
    PHP;

    expect(volatileDefaultRuleIds($code, new DatabaseContext('pgsql', '16.0')))->toBeEmpty();
});

it('registers the volatile default rule in the service container', function (): void {
    $code = volatileDefaultMigration("\$table->uuid('public_id')->default(DB::raw('gen_random_uuid()'));");

    /** @var RuleEngine $engine */
    $engine = app(RuleEngine::class);
    $violations = $engine->check((new MigrationAnalyzer)->analyzeCode($code), new DatabaseContext('pgsql', '16.0'));

    expect(array_map(fn ($violation) => $violation->ruleId, $violations))->toContain('add_column_volatile_default');
});
