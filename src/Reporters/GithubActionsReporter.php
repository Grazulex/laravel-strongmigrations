<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Reporters;

use Grazulex\StrongMigrations\Contracts\ReporterInterface;
use Grazulex\StrongMigrations\Data\Severity;
use Grazulex\StrongMigrations\Data\Violation;

class GithubActionsReporter implements ReporterInterface
{
    /**
     * @param  array<Violation>  $violations
     */
    public function report(array $violations, string $migrationName): string
    {
        if ($violations === []) {
            return '';
        }

        $lines = [];

        foreach ($violations as $violation) {
            $level = $this->severityToLevel($violation->severity);
            $title = $violation->ruleId;
            $message = str_replace("\n", '%0A', $violation->message);

            $lines[] = "::{$level} file={$migrationName},title={$title}::{$message}";
        }

        return implode("\n", $lines);
    }

    private function severityToLevel(Severity $severity): string
    {
        return match ($severity) {
            Severity::High, Severity::Medium => 'error',
            Severity::Low, Severity::Warning => 'warning',
        };
    }
}
