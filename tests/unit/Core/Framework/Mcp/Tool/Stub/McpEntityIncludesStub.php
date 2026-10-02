<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Mcp\Tool\Stub;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\Tool\McpEntityIncludes;

/**
 * Composes the trait the way a tool does and opens its private entry points to the test, so the test class
 * itself stays free of production traits.
 *
 * @internal
 */
#[Package('framework')]
final class McpEntityIncludesStub
{
    use McpEntityIncludes;

    public function launchApplyDefaultIncludes(EntityDefinition $definition, Criteria $criteria): void
    {
        $this->applyDefaultIncludes($definition, $criteria);
    }

    /**
     * @return array<string, list<string>>
     */
    public function launchBuildDefaultIncludes(EntityDefinition $definition, Criteria $criteria): array
    {
        return $this->buildDefaultIncludes($definition, $criteria);
    }
}
