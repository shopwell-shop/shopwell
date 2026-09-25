<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Seo\MainCategory\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Seo\MainCategory\SalesChannel\SalesChannelMainCategoryDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SalesChannelMainCategoryDefinition::class)]
class SalesChannelMainCategoryDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheSalesChannel(): void
    {
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        (new SalesChannelMainCategoryDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('salesChannelId', $context->getSalesChannelId())], $criteria->getFilters());
    }
}
