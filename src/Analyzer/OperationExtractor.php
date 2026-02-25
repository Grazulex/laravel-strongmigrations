<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Analyzer;

use Grazulex\StrongMigrations\Data\Operation;
use Grazulex\StrongMigrations\Data\OperationType;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;

class OperationExtractor extends NodeVisitorAbstract
{
    private const COLUMN_TYPE_METHODS = [
        'string', 'text', 'integer', 'bigInteger', 'smallInteger', 'tinyInteger',
        'mediumInteger', 'unsignedBigInteger', 'unsignedInteger', 'unsignedSmallInteger',
        'unsignedTinyInteger', 'unsignedMediumInteger', 'float', 'double', 'decimal',
        'unsignedDecimal', 'boolean', 'date', 'dateTime', 'dateTimeTz', 'time', 'timeTz',
        'timestamp', 'timestampTz', 'char', 'longText', 'mediumText', 'tinyText',
        'binary', 'uuid', 'ulid', 'enum', 'set', 'year',
    ];

    private const AUTO_INCREMENT_METHODS = [
        'increments', 'bigIncrements', 'smallIncrements', 'tinyIncrements',
        'mediumIncrements', 'id',
    ];

    /**
     * @var array<Operation>
     */
    private array $operations = [];

    private bool $insideSafetyAssured = false;

    private bool $hasSchemaOperations = false;

    private bool $hasDataOperations = false;

    /**
     * @return array<Operation>
     */
    public function getOperations(): array
    {
        $operations = $this->operations;

        if ($this->hasSchemaOperations && $this->hasDataOperations) {
            $operations[] = new Operation(
                type: OperationType::Backfill,
                insideSafetyAssured: false,
            );
        }

        return $operations;
    }

    public function enterNode(Node $node): ?int
    {
        if ($this->isSafetyAssuredCall($node)) {
            $this->insideSafetyAssured = true;

            return null;
        }

        if ($node instanceof StaticCall) {
            $this->processStaticCall($node);
        }

        // Process method chains from the outermost call
        // Only process if this MethodCall is NOT the var of another MethodCall
        // (i.e., it's the top of the chain)
        if ($node instanceof Node\Stmt\Expression && $node->expr instanceof MethodCall) {
            $this->processMethodChainFromTop($node->expr);
        }

        // Handle standalone MethodCalls (non-chain calls like dropColumn, renameColumn)
        if ($node instanceof MethodCall) {
            $this->processStandaloneMethodCall($node);
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        if ($this->isSafetyAssuredCall($node)) {
            $this->insideSafetyAssured = false;
        }

        return null;
    }

    private function isSafetyAssuredCall(Node $node): bool
    {
        if (! $node instanceof StaticCall) {
            return false;
        }

        $className = $this->resolveClassName($node);
        $methodName = $this->resolveMethodName($node);

        return ($className === 'StrongMigrations' || str_ends_with($className ?? '', '\StrongMigrations'))
            && $methodName === 'safetyAssured';
    }

    private function processStaticCall(StaticCall $node): void
    {
        $className = $this->resolveClassName($node);
        $methodName = $this->resolveMethodName($node);

        if ($className === null || $methodName === null) {
            return;
        }

        if ($className === 'Schema' || str_ends_with($className, '\Schema')) {
            $this->processSchemaCall($methodName, $node);
        }

        if ($className === 'DB' || str_ends_with($className, '\DB')) {
            $this->processDbCall($methodName, $node);
        }
    }

    private function processSchemaCall(string $method, StaticCall $node): void
    {
        $this->hasSchemaOperations = true;

        match ($method) {
            'rename' => $this->addRenameTableOperation($node),
            'drop', 'dropIfExists' => $this->addDropTableOperation($node),
            default => null,
        };
    }

    private function processDbCall(string $method, StaticCall $node): void
    {
        if (in_array($method, ['statement', 'unprepared'], true)) {
            $sql = $this->getFirstStringArg($node);
            $this->operations[] = new Operation(
                type: OperationType::RawSql,
                details: ['sql' => $sql],
                insideSafetyAssured: $this->insideSafetyAssured,
            );

            if ($sql !== null && preg_match('/^\s*(UPDATE|INSERT|DELETE)\b/i', $sql)) {
                $this->hasDataOperations = true;
            }
        }

        if ($method === 'table') {
            $this->hasDataOperations = true;
        }
    }

    /**
     * Process standalone method calls (not part of a column definition chain)
     */
    private function processStandaloneMethodCall(MethodCall $node): void
    {
        $methodName = $this->resolveMethodName($node);
        if ($methodName === null) {
            return;
        }

        match ($methodName) {
            'dropColumn' => $this->addDropColumnOperation($node),
            'renameColumn' => $this->addRenameColumnOperation($node),
            default => null,
        };
    }

    /**
     * Process a method chain from its outermost (topmost) call.
     * Walks down via ->var to collect all method names and find the column type.
     */
    private function processMethodChainFromTop(MethodCall $node): void
    {
        $chainMethods = $this->collectChainMethods($node);

        // Check for specific standalone operations first
        $methodNames = array_column($chainMethods, 'name');

        if (in_array('index', $methodNames, true)) {
            $this->addIndexOperation($this->findMethodCallInChain($node, 'index'));

            return;
        }

        if (in_array('unique', $methodNames, true)) {
            $this->addUniqueOperation($this->findMethodCallInChain($node, 'unique'));

            return;
        }

        if (in_array('foreign', $methodNames, true)) {
            $this->addForeignKeyOperation($this->findMethodCallInChain($node, 'foreign'));

            return;
        }

        if (in_array('json', $methodNames, true)) {
            $jsonCall = $this->findMethodCallInChain($node, 'json');
            $this->addJsonColumnOperation($jsonCall);

            return;
        }

        // Find the column type method in the chain
        $columnTypeMethod = null;
        $columnTypeCall = null;
        foreach ($chainMethods as $info) {
            if (in_array($info['name'], self::AUTO_INCREMENT_METHODS, true)) {
                $this->operations[] = new Operation(
                    type: OperationType::AddColumn,
                    column: $this->getFirstStringArg($info['node']),
                    details: ['auto_increment' => true, 'column_type' => $info['name']],
                    insideSafetyAssured: $this->insideSafetyAssured,
                );

                return;
            }

            if (in_array($info['name'], self::COLUMN_TYPE_METHODS, true)) {
                $columnTypeMethod = $info['name'];
                $columnTypeCall = $info['node'];
            }
        }

        if ($columnTypeMethod === null || $columnTypeCall === null) {
            return;
        }

        $columnName = $this->getFirstStringArg($columnTypeCall);

        $hasDefault = in_array('default', $methodNames, true);
        $hasChange = in_array('change', $methodNames, true);
        $isNullable = false;
        $hasNullableFalse = false;

        // Check nullable() args
        foreach ($chainMethods as $info) {
            if ($info['name'] === 'nullable') {
                $args = $info['node']->getArgs();
                if ($args === []) {
                    $isNullable = true;
                } else {
                    $argValue = $args[0]->value;
                    if ($argValue instanceof Node\Expr\ConstFetch && $argValue->name->toString() === 'false') {
                        $hasNullableFalse = true;
                    } else {
                        $isNullable = true;
                    }
                }
            }
        }

        $details = [
            'column_type' => $columnTypeMethod,
            'has_default' => $hasDefault,
            'is_nullable' => $isNullable,
            'has_change' => $hasChange,
        ];

        if ($hasChange) {
            $this->operations[] = new Operation(
                type: OperationType::ChangeColumn,
                column: $columnName,
                details: $details,
                insideSafetyAssured: $this->insideSafetyAssured,
            );

            if ($hasNullableFalse) {
                $this->operations[] = new Operation(
                    type: OperationType::SetNotNull,
                    column: $columnName,
                    details: $details,
                    insideSafetyAssured: $this->insideSafetyAssured,
                );
            }

            return;
        }

        if ($hasDefault && ! $isNullable) {
            $this->operations[] = new Operation(
                type: OperationType::AddColumn,
                column: $columnName,
                details: array_merge($details, ['has_default_not_null' => true]),
                insideSafetyAssured: $this->insideSafetyAssured,
            );
        }
    }

    /**
     * Collect all method names and their nodes in a chain by walking down via ->var
     *
     * @return array<array{name: string, node: MethodCall}>
     */
    private function collectChainMethods(MethodCall $node): array
    {
        $methods = [];
        $current = $node;

        while ($current instanceof MethodCall) {
            $name = $this->resolveMethodName($current);
            if ($name !== null) {
                $methods[] = ['name' => $name, 'node' => $current];
            }
            $current = $current->var;
        }

        return $methods;
    }

    private function findMethodCallInChain(MethodCall $node, string $targetMethod): MethodCall
    {
        $current = $node;
        while ($current instanceof MethodCall) {
            if ($this->resolveMethodName($current) === $targetMethod) {
                return $current;
            }
            $current = $current->var;
        }

        return $node;
    }

    private function addIndexOperation(MethodCall $node): void
    {
        $indexColumns = $this->resolveIndexColumns($node);

        $this->operations[] = new Operation(
            type: OperationType::AddIndex,
            details: ['columns' => $indexColumns, 'column_count' => count($indexColumns)],
            insideSafetyAssured: $this->insideSafetyAssured,
        );
    }

    private function addUniqueOperation(MethodCall $node): void
    {
        $columnName = $this->getFirstStringArg($node);

        $this->operations[] = new Operation(
            type: OperationType::AddUniqueConstraint,
            column: $columnName,
            insideSafetyAssured: $this->insideSafetyAssured,
        );
    }

    private function addForeignKeyOperation(MethodCall $node): void
    {
        $columnName = $this->getFirstStringArg($node);

        $this->operations[] = new Operation(
            type: OperationType::AddForeignKey,
            column: $columnName,
            insideSafetyAssured: $this->insideSafetyAssured,
        );
    }

    private function addJsonColumnOperation(MethodCall $node): void
    {
        $columnName = $this->getFirstStringArg($node);

        $this->operations[] = new Operation(
            type: OperationType::AddColumn,
            column: $columnName,
            details: ['column_type' => 'json'],
            insideSafetyAssured: $this->insideSafetyAssured,
        );
    }

    private function addRenameTableOperation(StaticCall $node): void
    {
        $args = $node->getArgs();
        $from = isset($args[0]) ? $this->resolveStringValue($args[0]->value) : null;
        $to = isset($args[1]) ? $this->resolveStringValue($args[1]->value) : null;

        $this->operations[] = new Operation(
            type: OperationType::RenameTable,
            table: $from,
            details: ['from' => $from, 'to' => $to],
            insideSafetyAssured: $this->insideSafetyAssured,
        );
    }

    private function addDropTableOperation(StaticCall $node): void
    {
        $tableName = $this->getFirstStringArg($node);

        $this->operations[] = new Operation(
            type: OperationType::DropTable,
            table: $tableName,
            insideSafetyAssured: $this->insideSafetyAssured,
        );
    }

    private function addDropColumnOperation(MethodCall $node): void
    {
        $columnName = $this->getFirstStringArg($node);

        $this->operations[] = new Operation(
            type: OperationType::RemoveColumn,
            column: $columnName,
            insideSafetyAssured: $this->insideSafetyAssured,
        );
    }

    private function addRenameColumnOperation(MethodCall $node): void
    {
        $args = $node->getArgs();
        $from = isset($args[0]) ? $this->resolveStringValue($args[0]->value) : null;
        $to = isset($args[1]) ? $this->resolveStringValue($args[1]->value) : null;

        $this->operations[] = new Operation(
            type: OperationType::RenameColumn,
            column: $from,
            details: ['from' => $from, 'to' => $to],
            insideSafetyAssured: $this->insideSafetyAssured,
        );
    }

    /**
     * @return array<string>
     */
    private function resolveIndexColumns(MethodCall $node): array
    {
        $args = $node->getArgs();
        if (! isset($args[0])) {
            return [];
        }

        $arg = $args[0]->value;

        if ($arg instanceof String_) {
            return [$arg->value];
        }

        if ($arg instanceof Node\Expr\Array_) {
            $columns = [];
            foreach ($arg->items as $item) {
                if ($item->value instanceof String_) {
                    $columns[] = $item->value->value;
                }
            }

            return $columns;
        }

        return [];
    }

    private function resolveClassName(StaticCall $node): ?string
    {
        if ($node->class instanceof Node\Name) {
            return $node->class->toString();
        }

        return null;
    }

    private function resolveMethodName(StaticCall|MethodCall $node): ?string
    {
        if ($node->name instanceof Node\Identifier) {
            return $node->name->toString();
        }

        return null;
    }

    private function getFirstStringArg(StaticCall|MethodCall $node): ?string
    {
        $args = $node->getArgs();
        if (! isset($args[0])) {
            return null;
        }

        return $this->resolveStringValue($args[0]->value);
    }

    private function resolveStringValue(Node\Expr $expr): ?string
    {
        if ($expr instanceof String_) {
            return $expr->value;
        }

        return null;
    }
}
