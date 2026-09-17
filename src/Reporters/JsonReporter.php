<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Reporters;

use Grazulex\StrongMigrations\Contracts\ReporterInterface;
use Grazulex\StrongMigrations\Data\Violation;

class JsonReporter implements ReporterInterface
{
    /**
     * @param  array<Violation>  $violations
     */
    public function report(array $violations, string $migrationName): string
    {
        $data = [
            'migration' => $migrationName,
            'violations_count' => count($violations),
            'violations' => array_map(fn (Violation $v): array => [
                'rule_id' => $v->ruleId,
                'severity' => $v->severity->value,
                'message' => $v->message,
                'safe_alternative' => $v->safeAlternative,
                'table' => $v->table,
                'column' => $v->column,
            ], $violations),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
