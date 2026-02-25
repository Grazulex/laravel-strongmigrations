<?php

declare(strict_types=1);

use Grazulex\StrongMigrations\Exceptions\DangerousOperationException;
use Grazulex\StrongMigrations\StrongMigrations;

beforeEach(function (): void {
    StrongMigrations::reset();
});

it('sets and resets safety assured flag', function (): void {
    expect(StrongMigrations::isSafetyAssured())->toBeFalse();

    StrongMigrations::safetyAssured(function (): void {
        expect(StrongMigrations::isSafetyAssured())->toBeTrue();
    });

    expect(StrongMigrations::isSafetyAssured())->toBeFalse();
});

it('resets safety assured flag even on exception', function (): void {
    try {
        StrongMigrations::safetyAssured(function (): never {
            throw new RuntimeException('Test error');
        });
    } catch (RuntimeException) {
        // Expected
    }

    expect(StrongMigrations::isSafetyAssured())->toBeFalse();
});

it('returns callback result from safetyAssured', function (): void {
    $result = StrongMigrations::safetyAssured(fn (): string => 'test-value');

    expect($result)->toBe('test-value');
});

it('stops with exception', function (): void {
    StrongMigrations::stop('Custom error message');
})->throws(DangerousOperationException::class, 'Custom error message');

it('manages custom checks', function (): void {
    expect(StrongMigrations::getCustomChecks())->toBeEmpty();

    StrongMigrations::addCheck(function (): void {});
    StrongMigrations::addCheck(function (): void {});

    expect(StrongMigrations::getCustomChecks())->toHaveCount(2);
});

it('manages custom messages', function (): void {
    expect(StrongMigrations::getCustomMessage())->toBeNull();

    StrongMigrations::setMessage('Custom message');
    expect(StrongMigrations::getCustomMessage())->toBe('Custom message');

    StrongMigrations::resetCustomMessage();
    expect(StrongMigrations::getCustomMessage())->toBeNull();
});

it('resets all state', function (): void {
    StrongMigrations::addCheck(function (): void {});
    StrongMigrations::setMessage('test');

    StrongMigrations::reset();

    expect(StrongMigrations::isSafetyAssured())->toBeFalse();
    expect(StrongMigrations::getCustomChecks())->toBeEmpty();
    expect(StrongMigrations::getCustomMessage())->toBeNull();
});
