<?php

declare(strict_types=1);

namespace Grazulex\StrongMigrations\Analyzer;

use Grazulex\StrongMigrations\Data\Operation;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class MigrationAnalyzer
{
    private readonly Parser $parser;

    public function __construct(?Parser $parser = null)
    {
        $this->parser = $parser ?? (new ParserFactory)->createForNewestSupportedVersion();
    }

    /**
     * @return array<Operation>
     */
    public function analyze(string $filePath): array
    {
        $code = file_get_contents($filePath);
        if ($code === false) {
            return [];
        }

        return $this->analyzeCode($code);
    }

    /**
     * @return array<Operation>
     */
    public function analyzeCode(string $code): array
    {
        $ast = $this->parser->parse($code);
        if ($ast === null) {
            return [];
        }

        $extractor = new OperationExtractor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($extractor);
        $traverser->traverse($ast);

        return $extractor->getOperations();
    }
}
