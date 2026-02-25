<?php

declare(strict_types=1);

// InstallCommand
it('install command publishes config and lang', function (): void {
    $this->artisan('strong-migrations:install')
        ->assertSuccessful();
});

// MigrateCheckCommand
it('migrate:check returns success when no migration files exist', function (): void {
    $tempDir = sys_get_temp_dir().'/sm_test_empty_'.uniqid();
    mkdir($tempDir, 0777, true);

    $this->artisan('migrate:check', ['--path' => $tempDir])
        ->assertSuccessful();

    rmdir($tempDir);
});

it('migrate:check returns failure for dangerous migrations', function (): void {
    $tempDir = sys_get_temp_dir().'/sm_test_danger_'.uniqid();
    mkdir($tempDir, 0777, true);

    file_put_contents($tempDir.'/2025_01_01_000000_drop_column.php', <<<'PHP'
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

    $this->artisan('migrate:check', ['--path' => $tempDir])
        ->assertFailed();

    unlink($tempDir.'/2025_01_01_000000_drop_column.php');
    rmdir($tempDir);
});

it('migrate:check supports json format', function (): void {
    $tempDir = sys_get_temp_dir().'/sm_test_json_'.uniqid();
    mkdir($tempDir, 0777, true);

    file_put_contents($tempDir.'/2025_01_01_000000_drop_column.php', <<<'PHP'
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

    $this->artisan('migrate:check', ['--path' => $tempDir, '--format' => 'json'])
        ->assertFailed();

    unlink($tempDir.'/2025_01_01_000000_drop_column.php');
    rmdir($tempDir);
});

it('migrate:check supports github format', function (): void {
    $tempDir = sys_get_temp_dir().'/sm_test_gh_'.uniqid();
    mkdir($tempDir, 0777, true);

    file_put_contents($tempDir.'/2025_01_01_000000_drop_column.php', <<<'PHP'
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

    $this->artisan('migrate:check', ['--path' => $tempDir, '--format' => 'github'])
        ->assertFailed();

    unlink($tempDir.'/2025_01_01_000000_drop_column.php');
    rmdir($tempDir);
});

it('migrate:check returns failure for non-existent path', function (): void {
    $this->artisan('migrate:check', ['--path' => '/tmp/nonexistent_dir_xyz'])
        ->assertFailed();
});

it('migrate:check passes for safe migrations', function (): void {
    $tempDir = sys_get_temp_dir().'/sm_test_safe_'.uniqid();
    mkdir($tempDir, 0777, true);

    file_put_contents($tempDir.'/2025_01_01_000000_safe_migration.php', <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::create('posts', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->timestamps();
            });
        }
    };
    PHP);

    $this->artisan('migrate:check', ['--path' => $tempDir])
        ->assertSuccessful();

    unlink($tempDir.'/2025_01_01_000000_safe_migration.php');
    rmdir($tempDir);
});
