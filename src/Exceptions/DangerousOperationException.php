<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Exceptions;

use Grazulex\StrongMigrations\Data\Violation;
use RuntimeException;

class DangerousOperationException extends RuntimeException
{
    /**
     * @var array<Violation>
     */
    protected array $violations = [];

    /**
     * @param  array<Violation>  $violations
     */
    public static function fromViolations(array $violations, string $migrationName): self
    {
        $messages = [];
        foreach ($violations as $violation) {
            $msg = sprintf('[%s] %s', $violation->ruleId, $violation->message);
            if ($violation->safeAlternative !== null) {
                $msg .= "\n\n".$violation->safeAlternative;
            }
            $messages[] = $msg;
        }

        $exception = new self(sprintf(
            "Dangerous operation detected in migration: %s\n\n%s",
            $migrationName,
            implode("\n\n---\n\n", $messages),
        ));

        $exception->violations = $violations;

        return $exception;
    }

    /**
     * @return array<Violation>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }
}
