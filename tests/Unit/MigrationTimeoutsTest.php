<?php

declare(strict_types=1);

use Grazulex\StrongMigrations\Support\MigrationTimeouts;
use Illuminate\Database\Connection;

/**
 * Connection double that records queries and answers settings lookups.
 */
function fakeTimeoutConnection(string $driver, array $current = []): Connection
{
    return new class($driver, $current) extends Connection
    {
        /** @var array<int, array{0: string, 1: array<int, mixed>}> */
        public array $queries = [];

        /**
         * @param  array<string, string>  $current
         */
        public function __construct(string $driver, public array $current)
        {
            parent::__construct(fn (): null => null, '', '', ['driver' => $driver, 'name' => $driver.'_fake']);
        }

        public function selectOne($query, $bindings = [], $useReadPdo = true)
        {
            $this->queries[] = [$query, $bindings];

            if ($query === 'select current_setting(?) as value') {
                return (object) ['value' => $this->current[$bindings[0]] ?? null];
            }

            if (preg_match('/^select @@session\.(\w+) as value$/', $query, $matches) === 1) {
                return (object) ['value' => $this->current[$matches[1]] ?? null];
            }

            return null;
        }

        public function statement($query, $bindings = [])
        {
            $this->queries[] = [$query, $bindings];

            return true;
        }
    };
}

beforeEach(function (): void {
    config()->set('strong-migrations.timeouts', [
        'statement_timeout' => '1h',
        'lock_timeout' => '10s',
        'lock_wait_timeout' => 10,
    ]);
});

it('applies and restores PostgreSQL timeouts', function (): void {
    $connection = fakeTimeoutConnection('pgsql', ['statement_timeout' => '0', 'lock_timeout' => '0']);
    $timeouts = new MigrationTimeouts;

    $timeouts->apply($connection);

    expect($connection->queries)->toBe([
        ['select current_setting(?) as value', ['statement_timeout']],
        ['select set_config(?, ?, false)', ['statement_timeout', '1h']],
        ['select current_setting(?) as value', ['lock_timeout']],
        ['select set_config(?, ?, false)', ['lock_timeout', '10s']],
    ]);

    $connection->queries = [];
    $timeouts->restore($connection);

    expect($connection->queries)->toBe([
        ['select set_config(?, ?, false)', ['statement_timeout', '0']],
        ['select set_config(?, ?, false)', ['lock_timeout', '0']],
    ]);
});

it('applies and restores the MySQL lock_wait_timeout', function (string $driver): void {
    $connection = fakeTimeoutConnection($driver, ['lock_wait_timeout' => '31536000']);
    $timeouts = new MigrationTimeouts;

    $timeouts->apply($connection);

    expect($connection->queries)->toBe([
        ['select @@session.lock_wait_timeout as value', []],
        ['set session lock_wait_timeout = 10', []],
    ]);

    $connection->queries = [];
    $timeouts->restore($connection);

    expect($connection->queries)->toBe([
        ['set session lock_wait_timeout = 31536000', []],
    ]);
})->with(['mysql', 'mariadb']);

it('does nothing on unsupported drivers', function (): void {
    $connection = fakeTimeoutConnection('sqlite');
    $timeouts = new MigrationTimeouts;

    $timeouts->apply($connection);
    $timeouts->restore($connection);

    expect($connection->queries)->toBe([]);
});

it('skips timeouts set to null', function (): void {
    config()->set('strong-migrations.timeouts', [
        'statement_timeout' => null,
        'lock_timeout' => '5s',
        'lock_wait_timeout' => null,
    ]);

    expect((new MigrationTimeouts)->settingsFor('pgsql'))->toBe(['lock_timeout' => '5s'])
        ->and((new MigrationTimeouts)->settingsFor('mysql'))->toBe([]);
});

it('accepts integer PostgreSQL values as milliseconds', function (): void {
    config()->set('strong-migrations.timeouts.lock_timeout', 5000);

    expect((new MigrationTimeouts)->settingsFor('pgsql')['lock_timeout'])->toBe('5000');
});

it('ignores an invalid MySQL lock_wait_timeout', function (mixed $value): void {
    config()->set('strong-migrations.timeouts.lock_wait_timeout', $value);

    expect((new MigrationTimeouts)->settingsFor('mysql'))->toBe([]);
})->with([0, -1, '10s', '']);

it('does not overwrite saved values when applied twice', function (): void {
    $connection = fakeTimeoutConnection('pgsql', ['statement_timeout' => '0', 'lock_timeout' => '0']);
    $timeouts = new MigrationTimeouts;

    $timeouts->apply($connection);
    $connection->current = ['statement_timeout' => '1h', 'lock_timeout' => '10s'];
    $timeouts->apply($connection);

    $connection->queries = [];
    $timeouts->restore($connection);

    expect($connection->queries)->toBe([
        ['select set_config(?, ?, false)', ['statement_timeout', '0']],
        ['select set_config(?, ?, false)', ['lock_timeout', '0']],
    ]);
});

it('does nothing when restoring without a previous apply', function (): void {
    $connection = fakeTimeoutConnection('pgsql');

    (new MigrationTimeouts)->restore($connection);

    expect($connection->queries)->toBe([]);
});

it('runs migrations on unsupported drivers without errors', function (): void {
    $tempDir = sys_get_temp_dir().'/sm_test_timeouts_'.uniqid();
    mkdir($tempDir, 0777, true);
    file_put_contents($tempDir.'/2026_01_01_000000_create_widgets_table.php', <<<'PHP'
    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void
        {
            Schema::create('widgets', function (Blueprint $table) {
                $table->id();
            });
        }
    };
    PHP);

    $this->artisan('migrate', ['--path' => $tempDir, '--realpath' => true])
        ->assertSuccessful();

    foreach (glob($tempDir.'/*.php') ?: [] as $file) {
        unlink($file);
    }

    rmdir($tempDir);
});
