<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Reporters;

use Grazulex\StrongMigrations\Contracts\ReporterInterface;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;

class ConsoleReporter implements ReporterInterface
{
    /**
     * @param  array<Violation>  $violations
     */
    public function report(array $violations, string $migrationName): string
    {
        if ($violations === []) {
            return "✓ {$migrationName}: No issues found.";
        }

        $lines = [];
        $lines[] = "✗ {$migrationName}: ".count($violations).' issue(s) found.';
        $lines[] = '';

        foreach ($violations as $violation) {
            $symbol = $this->severitySymbol($violation->severity);
            $lines[] = "  {$symbol} [{$violation->ruleId}] {$violation->message}";

            if ($violation->safeAlternative !== null) {
                $lines[] = "    → {$violation->safeAlternative}";
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function severitySymbol(Severity $severity): string
    {
        return match ($severity) {
            Severity::High => '🔴',
            Severity::Medium => '🟠',
            Severity::Low => '🟡',
            Severity::Warning => '⚠️',
        };
    }
}
