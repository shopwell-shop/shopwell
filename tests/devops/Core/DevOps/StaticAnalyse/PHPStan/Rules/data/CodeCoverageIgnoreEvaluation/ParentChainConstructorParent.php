<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

class ParentChainConstructorParent
{
    public function __construct(public readonly string $base)
    {
    }
}
