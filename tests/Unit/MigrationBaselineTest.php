<?php

declare(strict_types=1);

use Grazulex\StrongMigrations\Support\MigrationBaseline;

function writeDropColumnMigration(string $path): void
{
    file_put_contents($path, <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('email');
            });
        }
    };
    PHP);
}

function removeMigrationDirectory(string $dir): void
{
    foreach (glob($dir.'/*.php') ?: [] as $file) {
        unlink($file);
    }

    rmdir($dir);
}

it('does not skip anything without a baseline', function (): void {
    config()->set('strong-migrations.start_after', null);

    expect(MigrationBaseline::shouldSkip('/tmp/2020_01_01_000000_old.php'))->toBeFalse();
});

it('skips migrations up to and including the baseline', function (): void {
    config()->set('strong-migrations.start_after', '2025_01_01_000000');

    expect(MigrationBaseline::shouldSkip('/tmp/2024_12_31_235959_old.php'))->toBeTrue()
        ->and(MigrationBaseline::shouldSkip('/tmp/2025_01_01_000000_same.php'))->toBeTrue()
        ->and(MigrationBaseline::shouldSkip('/tmp/2025_01_01_000001_new.php'))->toBeFalse();
});

it('never skips migrations without a timestamp prefix', function (): void {
    expect(MigrationBaseline::shouldSkip('/tmp/create_users_table.php', '2025_01_01_000000'))->toBeFalse();
});

it('accepts an explicit baseline over the configuration', function (): void {
    config()->set('strong-migrations.start_after', null);

    expect(MigrationBaseline::shouldSkip('/tmp/2024_01_01_000000_old.php', '2025_01_01_000000'))->toBeTrue();
});

it('ignores an empty baseline', function (): void {
    config()->set('strong-migrations.start_after', '');

    expect(MigrationBaseline::configuredStartAfter())->toBeNull()
        ->and(MigrationBaseline::shouldSkip('/tmp/2020_01_01_000000_old.php'))->toBeFalse();
});

it('migrate:check skips migrations before start_after', function (): void {
    $tempDir = sys_get_temp_dir().'/sm_test_baseline_'.uniqid();
    mkdir($tempDir, 0777, true);
    writeDropColumnMigration($tempDir.'/2024_01_01_000000_drop_column.php');

    config()->set('strong-migrations.start_after', '2025_01_01_000000');

    $this->artisan('migrate:check', ['--path' => $tempDir])
        ->assertSuccessful();

    removeMigrationDirectory($tempDir);
});

it('migrate:check still reports migrations after start_after', function (): void {
    $tempDir = sys_get_temp_dir().'/sm_test_baseline_new_'.uniqid();
    mkdir($tempDir, 0777, true);
    writeDropColumnMigration($tempDir.'/2024_01_01_000000_old_drop_column.php');
    writeDropColumnMigration($tempDir.'/2025_06_01_000000_new_drop_column.php');

    config()->set('strong-migrations.start_after', '2025_01_01_000000');

    $this->artisan('migrate:check', ['--path' => $tempDir, '--format' => 'json'])
        ->expectsOutputToContain('2025_06_01_000000_new_drop_column.php')
        ->doesntExpectOutputToContain('2024_01_01_000000_old_drop_column.php')
        ->assertFailed();

    removeMigrationDirectory($tempDir);
});

it('migrate:check reports old migrations without start_after', function (): void {
    $tempDir = sys_get_temp_dir().'/sm_test_baseline_none_'.uniqid();
    mkdir($tempDir, 0777, true);
    writeDropColumnMigration($tempDir.'/2024_01_01_000000_drop_column.php');

    config()->set('strong-migrations.start_after', null);

    $this->artisan('migrate:check', ['--path' => $tempDir])
        ->assertFailed();

    removeMigrationDirectory($tempDir);
});
