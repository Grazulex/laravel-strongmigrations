<?php

declare(strict_types=1);

use Grazulex\StrongMigrations\Analyzer\MigrationAnalyzer;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Rules\PostgreSQL\AddForeignKeyRule;
use Grazulex\StrongMigrations\Rules\PostgreSQL\AddIndexNonConcurrentRule;
use Grazulex\StrongMigrations\Rules\PostgreSQL\AddUniqueConstraintRule;
use Grazulex\StrongMigrations\Rules\RuleEngine;

function createPgsqlConstraintEngine(): RuleEngine
{
    $engine = new RuleEngine;
    $engine->addRule(new AddIndexNonConcurrentRule);
    $engine->addRule(new AddUniqueConstraintRule);
    $engine->addRule(new AddForeignKeyRule);

    return $engine;
}

/**
 * @param  array<Operation>  $operations
 * @return array<Operation>
 */
function constraintOperations(array $operations): array
{
    return array_values(array_filter($operations, fn (Operation $op): bool => in_array($op->type, [
        OperationType::AddIndex,
        OperationType::AddUniqueConstraint,
        OperationType::AddForeignKey,
    ], true)));
}

it('marks index, unique and foreign key inside Schema::create as new table operations', function (): void {
    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::create('posts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('slug');
                $table->index('user_id');
                $table->unique('slug');
                $table->foreign('user_id')->references('id')->on('users');
            });
        }
    };
    PHP;

    $operations = constraintOperations((new MigrationAnalyzer)->analyzeCode($code));

    expect($operations)->toHaveCount(3);
    foreach ($operations as $operation) {
        expect($operation->details['new_table'] ?? false)->toBeTrue();
        expect($operation->table)->toBe('posts');
    }
});

it('does not mark index, unique and foreign key inside Schema::table as new table operations', function (): void {
    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::create('tags', function (Blueprint $table) {
                $table->id();
            });

            Schema::table('posts', function (Blueprint $table) {
                $table->index('user_id');
                $table->unique('slug');
                $table->foreign('user_id')->references('id')->on('users');
            });
        }
    };
    PHP;

    $operations = constraintOperations((new MigrationAnalyzer)->analyzeCode($code));

    expect($operations)->toHaveCount(3);
    foreach ($operations as $operation) {
        expect($operation->details)->not->toHaveKey('new_table');
        expect($operation->table)->toBeNull();
    }
});

it('pgsql: reports no violation for index, unique and foreign key on a new table', function (): void {
    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::create('posts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('slug');
                $table->index(['user_id', 'slug']);
                $table->unique('slug');
                $table->foreign('user_id')->references('id')->on('users');
            });
        }
    };
    PHP;

    $operations = (new MigrationAnalyzer)->analyzeCode($code);
    $violations = createPgsqlConstraintEngine()->check($operations, new DatabaseContext('pgsql'));

    expect($violations)->toBeEmpty();
});

it('pgsql: reports no violation on a new table created through Schema::connection()', function (): void {
    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::connection('pgsql')->create('posts', function (Blueprint $table) {
                $table->index('user_id');
                $table->unique('slug');
                $table->foreign('user_id')->references('id')->on('users');
            });
        }
    };
    PHP;

    $operations = (new MigrationAnalyzer)->analyzeCode($code);
    $violations = createPgsqlConstraintEngine()->check($operations, new DatabaseContext('pgsql'));

    expect($violations)->toBeEmpty();
});

it('pgsql: still reports index, unique and foreign key on an existing table after a Schema::create', function (): void {
    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::create('tags', function (Blueprint $table) {
                $table->id();
                $table->index('name');
            });

            Schema::table('posts', function (Blueprint $table) {
                $table->index('user_id');
                $table->unique('slug');
                $table->foreign('user_id')->references('id')->on('users');
            });
        }
    };
    PHP;

    $operations = (new MigrationAnalyzer)->analyzeCode($code);
    $violations = createPgsqlConstraintEngine()->check($operations, new DatabaseContext('pgsql'));

    expect(array_map(fn ($violation) => $violation->ruleId, $violations))->toBe([
        'add_index_non_concurrent',
        'add_unique_constraint',
        'add_foreign_key',
    ]);
});
