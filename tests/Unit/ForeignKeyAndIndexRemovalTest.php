<?php

declare(strict_types=1);

use Grazulex\StrongMigrations\Analyzer\MigrationAnalyzer;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Rules\Common\RemoveColumnRule;
use Grazulex\StrongMigrations\Rules\PostgreSQL\AddForeignKeyRule;
use Grazulex\StrongMigrations\Rules\RuleEngine;

/**
 * @return array<Operation>
 */
function operationsOfType(string $body, OperationType $type, string $method = 'table'): array
{
    $code = <<<PHP
    <?php
    use Illuminate\\Database\\Migrations\\Migration;
    use Illuminate\\Database\\Schema\\Blueprint;
    use Illuminate\\Support\\Facades\\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::{$method}('posts', function (Blueprint \$table) {
                {$body}
            });
        }
    };
    PHP;

    $operations = (new MigrationAnalyzer)->analyzeCode($code);

    return array_values(array_filter($operations, fn (Operation $op): bool => $op->type === $type));
}

it('detects foreignId()->constrained() as a foreign key', function (): void {
    $operations = operationsOfType("\$table->foreignId('user_id')->constrained();", OperationType::AddForeignKey);

    expect($operations)->toHaveCount(1)
        ->and($operations[0]->column)->toBe('user_id')
        ->and($operations[0]->table)->toBeNull()
        ->and($operations[0]->details['constrained'])->toBeTrue()
        ->and($operations[0]->details['column_type'])->toBe('foreignId')
        ->and($operations[0]->details['referenced_table'])->toBeNull()
        ->and($operations[0]->details)->not->toHaveKey('new_table');
});

it('captures the referenced table given to constrained()', function (): void {
    $operations = operationsOfType(
        "\$table->foreignId('author_id')->nullable()->constrained('users')->cascadeOnDelete();",
        OperationType::AddForeignKey,
    );

    expect($operations)->toHaveCount(1)
        ->and($operations[0]->column)->toBe('author_id')
        ->and($operations[0]->details['referenced_table'])->toBe('users');
});

it('detects foreignId()->references()->on() as a foreign key', function (): void {
    $operations = operationsOfType(
        "\$table->foreignId('user_id')->references('id')->on('users');",
        OperationType::AddForeignKey,
    );

    expect($operations)->toHaveCount(1)
        ->and($operations[0]->column)->toBe('user_id')
        ->and($operations[0]->details['constrained'])->toBeFalse()
        ->and($operations[0]->details['referenced_table'])->toBe('users');
});

it('detects foreignUuid, foreignUlid and foreignIdFor with constrained()', function (): void {
    $operations = operationsOfType(
        "\$table->foreignUuid('team_id')->constrained();\n"
        ."\$table->foreignUlid('org_id')->constrained();\n"
        ."\$table->foreignIdFor(User::class, 'owner_id')->constrained();\n"
        .'$table->foreignIdFor(User::class)->constrained();',
        OperationType::AddForeignKey,
    );

    expect(array_map(fn (Operation $op): ?string => $op->column, $operations))
        ->toBe(['team_id', 'org_id', 'owner_id', null]);
});

it('does not report a foreignId without constrained() as a foreign key', function (): void {
    $operations = operationsOfType("\$table->foreignId('user_id');", OperationType::AddForeignKey);

    expect($operations)->toBeEmpty();
});

it('keeps index detection on a constrained foreignId chain', function (): void {
    $code = "\$table->foreignId('user_id')->constrained()->index();";

    expect(operationsOfType($code, OperationType::AddForeignKey))->toHaveCount(1)
        ->and(operationsOfType($code, OperationType::AddIndex))->toHaveCount(1);
});

it('marks foreignId()->constrained() inside Schema::create as a new table operation', function (): void {
    $operations = operationsOfType(
        "\$table->id();\n\$table->foreignId('user_id')->constrained();",
        OperationType::AddForeignKey,
        'create',
    );

    expect($operations)->toHaveCount(1)
        ->and($operations[0]->table)->toBe('posts')
        ->and($operations[0]->details['new_table'] ?? false)->toBeTrue();
});

it('pgsql: reports foreignId()->constrained() on an existing table only', function (): void {
    $engine = new RuleEngine;
    $engine->addRule(new AddForeignKeyRule);
    $context = new DatabaseContext('pgsql');

    $existing = operationsOfType("\$table->foreignId('user_id')->constrained();", OperationType::AddForeignKey);
    $created = operationsOfType("\$table->foreignId('user_id')->constrained();", OperationType::AddForeignKey, 'create');

    $violations = $engine->check($existing, $context);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->ruleId)->toBe('add_foreign_key')
        ->and($violations[0]->column)->toBe('user_id')
        ->and($engine->check($created, $context))->toBeEmpty();
});

it('detects dropForeign with a column list or a constraint name', function (): void {
    $operations = operationsOfType(
        "\$table->dropForeign(['user_id']);\n\$table->dropForeign('posts_author_id_foreign');",
        OperationType::RemoveForeignKey,
    );

    expect($operations)->toHaveCount(2)
        ->and($operations[0]->column)->toBe('user_id')
        ->and($operations[0]->details['columns'])->toBe(['user_id'])
        ->and($operations[0]->details['foreign_key_name'])->toBeNull()
        ->and($operations[1]->column)->toBeNull()
        ->and($operations[1]->details['columns'])->toBe([])
        ->and($operations[1]->details['foreign_key_name'])->toBe('posts_author_id_foreign');
});

it('detects dropConstrainedForeignId as a foreign key and column removal', function (): void {
    $body = "\$table->dropConstrainedForeignId('user_id');";

    $foreignKeys = operationsOfType($body, OperationType::RemoveForeignKey);
    $columns = operationsOfType($body, OperationType::RemoveColumn);

    expect($foreignKeys)->toHaveCount(1)
        ->and($foreignKeys[0]->column)->toBe('user_id')
        ->and($columns)->toHaveCount(1)
        ->and($columns[0]->column)->toBe('user_id');
});

it('detects dropForeignIdFor and dropConstrainedForeignIdFor', function (): void {
    $body = "\$table->dropForeignIdFor(User::class, 'owner_id');\n"
        ."\$table->dropConstrainedForeignIdFor(Team::class, 'team_id');";

    $foreignKeys = operationsOfType($body, OperationType::RemoveForeignKey);
    $columns = operationsOfType($body, OperationType::RemoveColumn);

    expect(array_map(fn (Operation $op): ?string => $op->column, $foreignKeys))->toBe(['owner_id', 'team_id'])
        ->and(array_map(fn (Operation $op): ?string => $op->column, $columns))->toBe(['team_id']);
});

it('reports the column dropped by dropConstrainedForeignId through remove_column', function (): void {
    $engine = new RuleEngine;
    $engine->addRule(new RemoveColumnRule);

    $operations = operationsOfType("\$table->dropConstrainedForeignId('user_id');", OperationType::RemoveColumn);
    $violations = $engine->check($operations, new DatabaseContext('mysql'));

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->ruleId)->toBe('remove_column');
});

it('detects index removals', function (): void {
    $operations = operationsOfType(
        "\$table->dropIndex(['user_id', 'slug']);\n"
        ."\$table->dropIndex('posts_title_index');\n"
        ."\$table->dropUnique(['slug']);\n"
        ."\$table->dropPrimary();\n"
        ."\$table->dropFullText('posts_body_fulltext');\n"
        ."\$table->dropSpatialIndex(['location']);",
        OperationType::RemoveIndex,
    );

    expect(array_map(fn (Operation $op): string => $op->details['index_type'], $operations))
        ->toBe(['index', 'index', 'unique', 'primary', 'fulltext', 'spatial'])
        ->and($operations[0]->column)->toBe('user_id')
        ->and($operations[0]->details['columns'])->toBe(['user_id', 'slug'])
        ->and($operations[0]->details['index_name'])->toBeNull()
        ->and($operations[1]->column)->toBeNull()
        ->and($operations[1]->details['index_name'])->toBe('posts_title_index')
        ->and($operations[3]->details['columns'])->toBe([]);
});

it('marks removals inside safetyAssured', function (): void {
    $code = <<<'PHP'
    <?php
    use Grazulex\StrongMigrations\StrongMigrations;
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            StrongMigrations::safetyAssured(function () {
                Schema::table('posts', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                    $table->dropIndex(['user_id']);
                });
            });
        }
    };
    PHP;

    $operations = array_values(array_filter(
        (new MigrationAnalyzer)->analyzeCode($code),
        fn (Operation $op): bool => in_array($op->type, [OperationType::RemoveForeignKey, OperationType::RemoveIndex], true),
    ));

    expect($operations)->toHaveCount(2);
    foreach ($operations as $operation) {
        expect($operation->insideSafetyAssured)->toBeTrue();
    }
});
