<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\ProductStream\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\ProductStream\Service\AbstractProductStreamBuilder;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopwell\Core\Framework\Feature\FeatureException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AbstractProductStreamBuilder::class)]
class AbstractProductStreamBuilderTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testForwardsLegacyBuildFiltersCallToEnrichCriteria(): void
    {
        $filter = new EqualsFilter('active', true);
        $builder = new class($filter) extends AbstractProductStreamBuilder {
            public function __construct(private readonly Filter $filter)
            {
            }

            public function enrichCriteria(Criteria $criteria, string $id, Context $context): void
            {
                $criteria->addFilter($this->filter);
            }
        };

        static::assertSame([$filter], $builder->buildFilters('stream-id', Context::createDefaultContext()));
    }

    public function testLegacyBuildFiltersCallThrowsWhenV68IsActive(): void
    {
        $builder = new class extends AbstractProductStreamBuilder {
            public function enrichCriteria(Criteria $criteria, string $id, Context $context): void
            {
            }
        };

        $this->expectException(FeatureException::class);

        $builder->buildFilters('stream-id', Context::createDefaultContext());
    }
}
