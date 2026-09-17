<?php

declare(strict_types=1);

use Grazulex\StrongMigrations\Analyzer\MigrationAnalyzer;
use Grazulex\StrongMigrations\Data\OperationType;

it('detects dropColumn operations', function (): void {
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
                $table->dropColumn('legacy_field');
            });
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    expect($operations)->toHaveCount(1);
    expect($operations[0]->type)->toBe(OperationType::RemoveColumn);
    expect($operations[0]->column)->toBe('legacy_field');
});

it('detects renameColumn operations', function (): void {
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
                $table->renameColumn('name', 'full_name');
            });
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    expect($operations)->toHaveCount(1);
    expect($operations[0]->type)->toBe(OperationType::RenameColumn);
    expect($operations[0]->details['from'])->toBe('name');
    expect($operations[0]->details['to'])->toBe('full_name');
});

it('detects Schema::rename (rename table) operations', function (): void {
    $analyzer = new MigrationAnalyzer;

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::rename('users', 'members');
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    expect($operations)->toHaveCount(1);
    expect($operations[0]->type)->toBe(OperationType::RenameTable);
    expect($operations[0]->details['from'])->toBe('users');
    expect($operations[0]->details['to'])->toBe('members');
});

it('detects Schema::dropIfExists operations', function (): void {
    $analyzer = new MigrationAnalyzer;

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::dropIfExists('users');
            Schema::create('users', function ($table) {
                $table->id();
            });
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $dropOps = array_filter($operations, fn ($op) => $op->type === OperationType::DropTable);
    expect($dropOps)->toHaveCount(1);
});

it('detects backfill in same migration', function (): void {
    $analyzer = new MigrationAnalyzer;

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;
    use Illuminate\Support\Facades\DB;

    return new class extends Migration {
        public function up(): void
        {
            Schema::table('users', function (Blueprint $table) {
                $table->string('status')->nullable();
            });

            DB::table('users')->update(['status' => 'active']);
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $backfillOps = array_filter($operations, fn ($op) => $op->type === OperationType::Backfill);
    expect($backfillOps)->toHaveCount(1);
});

it('skips operations inside safetyAssured', function (): void {
    $analyzer = new MigrationAnalyzer;

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;
    use Grazulex\StrongMigrations\StrongMigrations;

    return new class extends Migration {
        public function up(): void
        {
            StrongMigrations::safetyAssured(function () {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropColumn('legacy_field');
                });
            });
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    foreach ($operations as $operation) {
        expect($operation->insideSafetyAssured)->toBeTrue();
    }
});

it('does not treat Schema introspection as a schema operation', function (): void {
    $analyzer = new MigrationAnalyzer;

    // Schema::hasTable/hasColumn are read-only guards, not schema mutations,
    // so combining them with a data update must NOT raise a backfill.
    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Support\Facades\Schema;
    use Illuminate\Support\Facades\DB;

    return new class extends Migration {
        public function up(): void
        {
            if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'status')) {
                return;
            }

            DB::table('users')->update(['status' => 'active']);
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $backfillOps = array_filter($operations, fn ($op) => $op->type === OperationType::Backfill);
    expect($backfillOps)->toHaveCount(0);
});

it('marks the backfill as safetyAssured when schema and data ops are wrapped', function (): void {
    $analyzer = new MigrationAnalyzer;

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Support\Facades\Schema;
    use Illuminate\Support\Facades\DB;
    use Grazulex\StrongMigrations\StrongMigrations;

    return new class extends Migration {
        public function up(): void
        {
            StrongMigrations::safetyAssured(function () {
                Schema::rename('old_users', 'users');
                DB::table('users')->update(['status' => 'active']);
            });
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $backfillOps = array_values(array_filter($operations, fn ($op) => $op->type === OperationType::Backfill));
    expect($backfillOps)->toHaveCount(1);
    expect($backfillOps[0]->insideSafetyAssured)->toBeTrue();
});

it('detects backfill when schema op uses Schema::connection()', function (): void {
    $analyzer = new MigrationAnalyzer;

    // connection() is a connection selector, not introspection: the schema
    // mutation on the chained builder must still be counted.
    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Support\Facades\Schema;
    use Illuminate\Support\Facades\DB;

    return new class extends Migration {
        public function up(): void
        {
            Schema::connection('mysql')->table('users', function ($t) {
                $t->string('foo')->nullable();
            });
            DB::table('users')->update(['foo' => 'x']);
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $backfillOps = array_filter($operations, fn ($op) => $op->type === OperationType::Backfill);
    expect($backfillOps)->toHaveCount(1);
});

it('does not mark the backfill assured when only one half is wrapped', function (): void {
    $analyzer = new MigrationAnalyzer;

    // The backfill is the unprotected part here, so it must NOT be suppressed.
    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Support\Facades\Schema;
    use Illuminate\Support\Facades\DB;
    use Grazulex\StrongMigrations\StrongMigrations;

    return new class extends Migration {
        public function up(): void
        {
            StrongMigrations::safetyAssured(function () {
                Schema::table('users', function ($t) { $t->string('foo')->nullable(); });
            });

            DB::table('users')->update(['foo' => 'x']);
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $backfillOps = array_values(array_filter($operations, fn ($op) => $op->type === OperationType::Backfill));
    expect($backfillOps)->toHaveCount(1);
    expect($backfillOps[0]->insideSafetyAssured)->toBeFalse();
});

it('does not treat introspection on Schema::connection() as a schema operation', function (): void {
    $analyzer = new MigrationAnalyzer;

    // The read-only guard is chained on connection(): it must still be
    // recognised as introspection, not as a schema mutation.
    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Support\Facades\Schema;
    use Illuminate\Support\Facades\DB;

    return new class extends Migration {
        public function up(): void
        {
            if (! Schema::connection('tenant')->hasTable('users')) {
                return;
            }

            DB::table('users')->update(['status' => 'active']);
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $backfillOps = array_filter($operations, fn ($op) => $op->type === OperationType::Backfill);
    expect($backfillOps)->toHaveCount(0);
});

it('detects rename table chained on Schema::connection()', function (): void {
    $analyzer = new MigrationAnalyzer;

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::connection('tenant')->rename('old_users', 'users');
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    expect($operations)->toHaveCount(1);
    expect($operations[0]->type)->toBe(OperationType::RenameTable);
    expect($operations[0]->table)->toBe('old_users');
    expect($operations[0]->details['to'])->toBe('users');
});

it('does not treat foreign key constraint toggles as a schema operation', function (): void {
    $analyzer = new MigrationAnalyzer;

    // disable/enableForeignKeyConstraints only toggle a session setting, they
    // do not mutate the schema. This is a very common data-migration pattern.
    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Support\Facades\Schema;
    use Illuminate\Support\Facades\DB;

    return new class extends Migration {
        public function up(): void
        {
            Schema::disableForeignKeyConstraints();
            DB::table('users')->update(['status' => 'active']);
            Schema::enableForeignKeyConstraints();
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $backfillOps = array_filter($operations, fn ($op) => $op->type === OperationType::Backfill);
    expect($backfillOps)->toHaveCount(0);
});

it('does not treat index listing introspection as a schema operation', function (): void {
    $analyzer = new MigrationAnalyzer;

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Support\Facades\Schema;
    use Illuminate\Support\Facades\DB;

    return new class extends Migration {
        public function up(): void
        {
            if (in_array('users_email_idx', Schema::getIndexListing('users'), true)) {
                return;
            }

            DB::table('users')->update(['status' => 'active']);
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    $backfillOps = array_filter($operations, fn ($op) => $op->type === OperationType::Backfill);
    expect($backfillOps)->toHaveCount(0);
});

it('detects raw SQL statements', function (): void {
    $analyzer = new MigrationAnalyzer;

    $code = <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Support\Facades\DB;

    return new class extends Migration {
        public function up(): void
        {
            DB::statement('ALTER TABLE users ADD COLUMN test VARCHAR(255)');
        }
    };
    PHP;

    $operations = $analyzer->analyzeCode($code);

    expect($operations)->toHaveCount(1);
    expect($operations[0]->type)->toBe(OperationType::RawSql);
});
