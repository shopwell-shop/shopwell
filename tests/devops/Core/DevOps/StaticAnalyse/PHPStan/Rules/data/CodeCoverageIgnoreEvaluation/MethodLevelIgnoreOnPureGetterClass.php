<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

class MethodLevelIgnoreOnPureGetterClass
{
    private string $name = '';

    /**
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return $this->name;
    }
}
