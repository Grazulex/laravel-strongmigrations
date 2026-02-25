<?php

declare(strict_types=1);

use Grazulex\StrongMigrations\Analyzer\MigrationAnalyzer;
use Grazulex\StrongMigrations\Data\OperationType;

it('detects add column with default not null', function (): void {
    $analyzer = new MigrationAnalyzer;

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::table('users', function (Blueprint $table) {
                $table->string('status')->default('active');
            });
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $addColumnOps = array_filter($operations, fn ($op) => $op->type === OperationType::AddColumn && ($op->details['has_default_not_null'] ?? false));
    expect($addColumnOps)->toHaveCount(1);
});

it('does not flag nullable column with default', function (): void {
    $analyzer = new MigrationAnalyzer;

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::table('users', function (Blueprint $table) {
                $table->string('status')->nullable()->default('active');
            });
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $addColumnOps = array_filter($operations, fn ($op) => $op->type === OperationType::AddColumn && ($op->details['has_default_not_null'] ?? false));
    expect($addColumnOps)->toBeEmpty();
});

it('detects change column with change()', function (): void {
    $analyzer = new MigrationAnalyzer;

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::table('users', function (Blueprint $table) {
                $table->text('bio')->change();
            });
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $changeOps = array_filter($operations, fn ($op) => $op->type === OperationType::ChangeColumn);
    expect($changeOps)->not->toBeEmpty();
});

it('detects set not null via nullable(false)->change()', function (): void {
    $analyzer = new MigrationAnalyzer;

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::table('users', function (Blueprint $table) {
                $table->string('email')->nullable(false)->change();
            });
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $setNotNullOps = array_filter($operations, fn ($op) => $op->type === OperationType::SetNotNull);
    expect($setNotNullOps)->not->toBeEmpty();
});

it('detects index operations', function (): void {
    $analyzer = new MigrationAnalyzer;

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['email', 'name']);
            });
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $indexOps = array_filter($operations, fn ($op) => $op->type === OperationType::AddIndex);
    expect($indexOps)->toHaveCount(1);
    $indexOp = array_values($indexOps)[0];
    expect($indexOp->details['columns'])->toBe(['email', 'name']);
    expect($indexOp->details['column_count'])->toBe(2);
});

it('detects wide index (> 3 columns)', function (): void {
    $analyzer = new MigrationAnalyzer;

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['a', 'b', 'c', 'd']);
            });
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $indexOps = array_filter($operations, fn ($op) => $op->type === OperationType::AddIndex);
    expect($indexOps)->toHaveCount(1);
    $indexOp = array_values($indexOps)[0];
    expect($indexOp->details['column_count'])->toBe(4);
});

it('detects unique constraint', function (): void {
    $analyzer = new MigrationAnalyzer;

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('email');
            });
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $uniqueOps = array_filter($operations, fn ($op) => $op->type === OperationType::AddUniqueConstraint);
    expect($uniqueOps)->toHaveCount(1);
});

it('detects foreign key', function (): void {
    $analyzer = new MigrationAnalyzer;

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users');
            });
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $fkOps = array_filter($operations, fn ($op) => $op->type === OperationType::AddForeignKey);
    expect($fkOps)->toHaveCount(1);
});

it('detects json column', function (): void {
    $analyzer = new MigrationAnalyzer;

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::table('users', function (Blueprint $table) {
                $table->json('metadata');
            });
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $jsonOps = array_filter($operations, fn ($op) => $op->type === OperationType::AddColumn && ($op->details['column_type'] ?? null) === 'json');
    expect($jsonOps)->toHaveCount(1);
});

it('detects auto-increment column', function (): void {
    $analyzer = new MigrationAnalyzer;

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::table('users', function (Blueprint $table) {
                $table->increments('legacy_id');
            });
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $autoIncOps = array_filter($operations, fn ($op) => $op->type === OperationType::AddColumn && ($op->details['auto_increment'] ?? false));
    expect($autoIncOps)->toHaveCount(1);
});
