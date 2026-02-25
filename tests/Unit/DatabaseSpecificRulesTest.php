<?php

declare(strict_types=1);

use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Rules\Common\AddAutoIncrementRule;
use Grazulex\StrongMigrations\Rules\Common\RawSqlRule;
use Grazulex\StrongMigrations\Rules\Common\WideIndexRule;
use Grazulex\StrongMigrations\Rules\MySQL\AddColumnWithDefaultRule;
use Grazulex\StrongMigrations\Rules\MySQL\ChangeColumnTypeRule;
use Grazulex\StrongMigrations\Rules\MySQL\SetNotNullRule as MySqlSetNotNullRule;
use Grazulex\StrongMigrations\Rules\PostgreSQL\AddForeignKeyRule;
use Grazulex\StrongMigrations\Rules\PostgreSQL\AddIndexNonConcurrentRule;
use Grazulex\StrongMigrations\Rules\PostgreSQL\AddUniqueConstraintRule;
use Grazulex\StrongMigrations\Rules\PostgreSQL\JsonColumnRule;
use Grazulex\StrongMigrations\Rules\PostgreSQL\SetNotNullRule as PgsqlSetNotNullRule;

// MySQL: AddColumnWithDefaultRule
it('mysql: flags add column with default not null on old MySQL', function (): void {
    $rule = new AddColumnWithDefaultRule;
    $context = new DatabaseContext('mysql', '5.7.0');
    $operation = new Operation(type: OperationType::AddColumn, column: 'status', details: ['has_default_not_null' => true]);

    $violation = $rule->check($operation, $context);
    expect($violation)->not->toBeNull();
    expect($violation->ruleId)->toBe('add_column_with_default');
});

it('mysql: skips add column with default on MySQL >= 8.0.12', function (): void {
    $rule = new AddColumnWithDefaultRule;
    $context = new DatabaseContext('mysql', '8.0.12');
    $operation = new Operation(type: OperationType::AddColumn, column: 'status', details: ['has_default_not_null' => true]);

    $violation = $rule->check($operation, $context);
    expect($violation)->toBeNull();
});

it('mariadb: skips add column with default on MariaDB >= 10.3.2', function (): void {
    $rule = new AddColumnWithDefaultRule;
    $context = new DatabaseContext('mariadb', '10.3.2');
    $operation = new Operation(type: OperationType::AddColumn, column: 'status', details: ['has_default_not_null' => true]);

    $violation = $rule->check($operation, $context);
    expect($violation)->toBeNull();
});

it('mariadb: flags add column with default on old MariaDB', function (): void {
    $rule = new AddColumnWithDefaultRule;
    $context = new DatabaseContext('mariadb', '10.2.0');
    $operation = new Operation(type: OperationType::AddColumn, column: 'status', details: ['has_default_not_null' => true]);

    $violation = $rule->check($operation, $context);
    expect($violation)->not->toBeNull();
});

// MySQL: ChangeColumnTypeRule
it('mysql: flags change column type', function (): void {
    $rule = new ChangeColumnTypeRule;
    $context = new DatabaseContext('mysql');
    $operation = new Operation(type: OperationType::ChangeColumn, column: 'bio');

    $violation = $rule->check($operation, $context);
    expect($violation)->not->toBeNull();
    expect($violation->ruleId)->toBe('change_column_type');
});

it('pgsql: flags change column type', function (): void {
    $rule = new ChangeColumnTypeRule;
    $context = new DatabaseContext('pgsql');
    $operation = new Operation(type: OperationType::ChangeColumn, column: 'bio');

    $violation = $rule->check($operation, $context);
    expect($violation)->not->toBeNull();
});

// MySQL: SetNotNullRule
it('mysql: flags set not null', function (): void {
    $rule = new MySqlSetNotNullRule;
    $context = new DatabaseContext('mysql');
    $operation = new Operation(type: OperationType::SetNotNull, column: 'email');

    $violation = $rule->check($operation, $context);
    expect($violation)->not->toBeNull();
    expect($violation->ruleId)->toBe('set_not_null_mysql');
});

// PostgreSQL: AddIndexNonConcurrentRule
it('pgsql: flags non-concurrent index', function (): void {
    $rule = new AddIndexNonConcurrentRule;
    $context = new DatabaseContext('pgsql');
    $operation = new Operation(type: OperationType::AddIndex, details: ['columns' => ['email'], 'column_count' => 1]);

    $violation = $rule->check($operation, $context);
    expect($violation)->not->toBeNull();
    expect($violation->ruleId)->toBe('add_index_non_concurrent');
});

// PostgreSQL: AddForeignKeyRule
it('pgsql: flags foreign key', function (): void {
    $rule = new AddForeignKeyRule;
    $context = new DatabaseContext('pgsql');
    $operation = new Operation(type: OperationType::AddForeignKey, column: 'user_id');

    $violation = $rule->check($operation, $context);
    expect($violation)->not->toBeNull();
    expect($violation->ruleId)->toBe('add_foreign_key');
});

// PostgreSQL: AddUniqueConstraintRule
it('pgsql: flags unique constraint', function (): void {
    $rule = new AddUniqueConstraintRule;
    $context = new DatabaseContext('pgsql');
    $operation = new Operation(type: OperationType::AddUniqueConstraint, column: 'email');

    $violation = $rule->check($operation, $context);
    expect($violation)->not->toBeNull();
    expect($violation->ruleId)->toBe('add_unique_constraint');
});

// PostgreSQL: JsonColumnRule
it('pgsql: flags json column', function (): void {
    $rule = new JsonColumnRule;
    $context = new DatabaseContext('pgsql');
    $operation = new Operation(type: OperationType::AddColumn, column: 'metadata', details: ['column_type' => 'json']);

    $violation = $rule->check($operation, $context);
    expect($violation)->not->toBeNull();
    expect($violation->ruleId)->toBe('json_column');
});

it('pgsql: does not flag non-json column', function (): void {
    $rule = new JsonColumnRule;
    $context = new DatabaseContext('pgsql');
    $operation = new Operation(type: OperationType::AddColumn, column: 'name', details: ['column_type' => 'string']);

    $violation = $rule->check($operation, $context);
    expect($violation)->toBeNull();
});

// PostgreSQL: SetNotNullRule
it('pgsql: flags set not null', function (): void {
    $rule = new PgsqlSetNotNullRule;
    $context = new DatabaseContext('pgsql');
    $operation = new Operation(type: OperationType::SetNotNull, column: 'email');

    $violation = $rule->check($operation, $context);
    expect($violation)->not->toBeNull();
    expect($violation->ruleId)->toBe('set_not_null_pgsql');
});

// Common: AddAutoIncrementRule
it('flags auto-increment on mysql', function (): void {
    $rule = new AddAutoIncrementRule;
    $context = new DatabaseContext('mysql');
    $operation = new Operation(type: OperationType::AddColumn, column: 'legacy_id', details: ['auto_increment' => true]);

    $violation = $rule->check($operation, $context);
    expect($violation)->not->toBeNull();
    expect($violation->ruleId)->toBe('add_auto_increment');
});

it('skips auto-increment on sqlite', function (): void {
    $rule = new AddAutoIncrementRule;
    $context = new DatabaseContext('sqlite');
    $operation = new Operation(type: OperationType::AddColumn, column: 'legacy_id', details: ['auto_increment' => true]);

    $violation = $rule->check($operation, $context);
    expect($violation)->toBeNull();
});

// Common: RawSqlRule
it('flags raw sql', function (): void {
    $rule = new RawSqlRule;
    $context = new DatabaseContext('mysql');
    $operation = new Operation(type: OperationType::RawSql, details: ['sql' => 'ALTER TABLE users ADD COLUMN test VARCHAR(255)']);

    $violation = $rule->check($operation, $context);
    expect($violation)->not->toBeNull();
    expect($violation->ruleId)->toBe('raw_sql');
});

// Common: WideIndexRule
it('flags wide index with > 3 columns', function (): void {
    $rule = new WideIndexRule;
    $context = new DatabaseContext('mysql');
    $operation = new Operation(type: OperationType::AddIndex, details: ['columns' => ['a', 'b', 'c', 'd'], 'column_count' => 4]);

    $violation = $rule->check($operation, $context);
    expect($violation)->not->toBeNull();
    expect($violation->ruleId)->toBe('wide_index');
});

it('allows index with <= 3 columns', function (): void {
    $rule = new WideIndexRule;
    $context = new DatabaseContext('mysql');
    $operation = new Operation(type: OperationType::AddIndex, details: ['columns' => ['a', 'b'], 'column_count' => 2]);

    $violation = $rule->check($operation, $context);
    expect($violation)->toBeNull();
});

// Driver filtering
it('pgsql rules do not apply to mysql', function (): void {
    $rule = new AddIndexNonConcurrentRule;
    expect($rule->appliesTo())->toBe(['pgsql']);
});

it('mysql rules do not apply to pgsql for add_column_with_default', function (): void {
    $rule = new AddColumnWithDefaultRule;
    expect($rule->appliesTo())->toBe(['mysql', 'mariadb']);
});
