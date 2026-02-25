<?php

declare(strict_types=1);

use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use Grazulex\StrongMigrations\Rules\Common\BackfillInMigrationRule;
use Grazulex\StrongMigrations\Rules\Common\CreateTableForceRule;
use Grazulex\StrongMigrations\Rules\Common\RemoveColumnRule;
use Grazulex\StrongMigrations\Rules\Common\RenameColumnRule;
use Grazulex\StrongMigrations\Rules\Common\RenameTableRule;
use Grazulex\StrongMigrations\Rules\RuleEngine;

function createEngine(): RuleEngine
{
    $engine = new RuleEngine;
    $engine->addRule(new RemoveColumnRule);
    $engine->addRule(new RenameColumnRule);
    $engine->addRule(new RenameTableRule);
    $engine->addRule(new CreateTableForceRule);
    $engine->addRule(new BackfillInMigrationRule);

    return $engine;
}

it('detects remove column violation', function (): void {
    $engine = createEngine();
    $context = new DatabaseContext('mysql');

    $operations = [
        new Operation(type: OperationType::RemoveColumn, column: 'legacy'),
    ];

    $violations = $engine->check($operations, $context);

    expect($violations)->toHaveCount(1);
    expect($violations[0]->ruleId)->toBe('remove_column');
});

it('detects rename column violation', function (): void {
    $engine = createEngine();
    $context = new DatabaseContext('mysql');

    $operations = [
        new Operation(type: OperationType::RenameColumn, column: 'name', details: ['from' => 'name', 'to' => 'full_name']),
    ];

    $violations = $engine->check($operations, $context);

    expect($violations)->toHaveCount(1);
    expect($violations[0]->ruleId)->toBe('rename_column');
});

it('detects rename table violation', function (): void {
    $engine = createEngine();
    $context = new DatabaseContext('pgsql');

    $operations = [
        new Operation(type: OperationType::RenameTable, table: 'users', details: ['from' => 'users', 'to' => 'members']),
    ];

    $violations = $engine->check($operations, $context);

    expect($violations)->toHaveCount(1);
    expect($violations[0]->ruleId)->toBe('rename_table');
});

it('detects create table force violation', function (): void {
    $engine = createEngine();
    $context = new DatabaseContext('mysql');

    $operations = [
        new Operation(type: OperationType::DropTable, table: 'users'),
    ];

    $violations = $engine->check($operations, $context);

    expect($violations)->toHaveCount(1);
    expect($violations[0]->ruleId)->toBe('create_table_force');
});

it('detects backfill in migration violation', function (): void {
    $engine = createEngine();
    $context = new DatabaseContext('mysql');

    $operations = [
        new Operation(type: OperationType::Backfill),
    ];

    $violations = $engine->check($operations, $context);

    expect($violations)->toHaveCount(1);
    expect($violations[0]->ruleId)->toBe('backfill_in_migration');
});

it('skips operations inside safetyAssured', function (): void {
    $engine = createEngine();
    $context = new DatabaseContext('mysql');

    $operations = [
        new Operation(type: OperationType::RemoveColumn, column: 'legacy', insideSafetyAssured: true),
    ];

    $violations = $engine->check($operations, $context);

    expect($violations)->toBeEmpty();
});

it('respects disabled checks', function (): void {
    $engine = createEngine();
    $engine->setDisabledChecks(['remove_column']);
    $context = new DatabaseContext('mysql');

    $operations = [
        new Operation(type: OperationType::RemoveColumn, column: 'legacy'),
    ];

    $violations = $engine->check($operations, $context);

    expect($violations)->toBeEmpty();
});

it('returns no violations for safe operations', function (): void {
    $engine = createEngine();
    $context = new DatabaseContext('mysql');

    $operations = [
        new Operation(type: OperationType::AddColumn, column: 'email'),
    ];

    $violations = $engine->check($operations, $context);

    expect($violations)->toBeEmpty();
});
