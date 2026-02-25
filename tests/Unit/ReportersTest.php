<?php

declare(strict_types=1);

use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;
use Grazulex\StrongMigrations\Reporters\ConsoleReporter;
use Grazulex\StrongMigrations\Reporters\GithubActionsReporter;
use Grazulex\StrongMigrations\Reporters\JsonReporter;

// ConsoleReporter
it('console reporter shows no issues for empty violations', function (): void {
    $reporter = new ConsoleReporter;
    $output = $reporter->report([], '2025_01_01_000000_test.php');

    expect($output)->toContain('No issues found');
});

it('console reporter formats violations with severity symbols', function (): void {
    $reporter = new ConsoleReporter;
    $violations = [
        new Violation(
            ruleId: 'remove_column',
            severity: Severity::High,
            message: 'Removing a column is dangerous',
            safeAlternative: 'Deploy in multiple steps',
        ),
    ];

    $output = $reporter->report($violations, '2025_01_01_000000_test.php');

    expect($output)
        ->toContain('1 issue(s) found')
        ->toContain('[remove_column]')
        ->toContain('Removing a column is dangerous')
        ->toContain('Deploy in multiple steps');
});

it('console reporter handles multiple violations', function (): void {
    $reporter = new ConsoleReporter;
    $violations = [
        new Violation(ruleId: 'rule_1', severity: Severity::High, message: 'Message 1'),
        new Violation(ruleId: 'rule_2', severity: Severity::Low, message: 'Message 2'),
    ];

    $output = $reporter->report($violations, 'migration.php');

    expect($output)
        ->toContain('2 issue(s) found')
        ->toContain('[rule_1]')
        ->toContain('[rule_2]');
});

// JsonReporter
it('json reporter outputs valid json', function (): void {
    $reporter = new JsonReporter;
    $violations = [
        new Violation(
            ruleId: 'remove_column',
            severity: Severity::High,
            message: 'Removing a column is dangerous',
            safeAlternative: 'Deploy in steps',
            column: 'email',
        ),
    ];

    $output = $reporter->report($violations, '2025_01_01_000000_test.php');
    $data = json_decode($output, true);

    expect($data)->toBeArray()
        ->and($data['migration'])->toBe('2025_01_01_000000_test.php')
        ->and($data['violations_count'])->toBe(1)
        ->and($data['violations'][0]['rule_id'])->toBe('remove_column')
        ->and($data['violations'][0]['severity'])->toBe('high')
        ->and($data['violations'][0]['column'])->toBe('email');
});

it('json reporter handles empty violations', function (): void {
    $reporter = new JsonReporter;
    $output = $reporter->report([], 'migration.php');
    $data = json_decode($output, true);

    expect($data['violations_count'])->toBe(0)
        ->and($data['violations'])->toBeEmpty();
});

// GithubActionsReporter
it('github actions reporter outputs error annotations', function (): void {
    $reporter = new GithubActionsReporter;
    $violations = [
        new Violation(
            ruleId: 'remove_column',
            severity: Severity::High,
            message: 'Removing a column is dangerous',
        ),
    ];

    $output = $reporter->report($violations, '2025_01_01_000000_test.php');

    expect($output)->toContain('::error file=2025_01_01_000000_test.php,title=remove_column::');
});

it('github actions reporter uses warning for low severity', function (): void {
    $reporter = new GithubActionsReporter;
    $violations = [
        new Violation(
            ruleId: 'wide_index',
            severity: Severity::Low,
            message: 'Index has too many columns',
        ),
    ];

    $output = $reporter->report($violations, 'migration.php');

    expect($output)->toContain('::warning file=migration.php,title=wide_index::');
});

it('github actions reporter returns empty string for no violations', function (): void {
    $reporter = new GithubActionsReporter;
    $output = $reporter->report([], 'migration.php');

    expect($output)->toBe('');
});
