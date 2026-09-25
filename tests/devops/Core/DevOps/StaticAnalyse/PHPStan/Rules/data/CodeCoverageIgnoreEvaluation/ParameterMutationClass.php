<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class ParameterMutationClass
{
    public function processCriteria(\ArrayObject $criteria, string $salesChannelId): void
    {
        $criteria->append($salesChannelId);
    }
}
