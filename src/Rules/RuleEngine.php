<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Rules;

use Grazulex\StrongMigrations\Contracts\RuleInterface;
use Grazulex\StrongMigrations\Data\DatabaseContext;
use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\Violation;

class RuleEngine
{
    /**
     * @var array<RuleInterface>
     */
    private array $rules = [];

    /**
     * @var array<string>
     */
    private array $disabledChecks = [];

    public function addRule(RuleInterface $rule): void
    {
        $this->rules[] = $rule;
    }

    /**
     * @param  array<string>  $checks
     */
    public function setDisabledChecks(array $checks): void
    {
        $this->disabledChecks = $checks;
    }

    /**
     * @param  array<Operation>  $operations
     * @return array<Violation>
     */
    public function check(array $operations, DatabaseContext $context): array
    {
        $violations = [];

        foreach ($operations as $operation) {
            if ($operation->insideSafetyAssured) {
                continue;
            }

            foreach ($this->rules as $rule) {
                if ($this->isRuleDisabled($rule)) {
                    continue;
                }

                if (! $this->ruleAppliesForDriver($rule, $context->driver)) {
                    continue;
                }

                $violation = $rule->check($operation, $context);
                if ($violation !== null) {
                    $violations[] = $violation;
                }
            }
        }

        return $violations;
    }

    private function isRuleDisabled(RuleInterface $rule): bool
    {
        return in_array($rule->id(), $this->disabledChecks, true);
    }

    private function ruleAppliesForDriver(RuleInterface $rule, string $driver): bool
    {
        $appliesTo = $rule->appliesTo();

        if ($appliesTo === [] || $appliesTo === ['*']) {
            return true;
        }

        return in_array($driver, $appliesTo, true);
    }
}
