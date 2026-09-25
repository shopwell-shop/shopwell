<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Shipping\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Shipping\SalesChannel\SalesChannelShippingMethodDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SalesChannelShippingMethodDefinition::class)]
class SalesChannelShippingMethodDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheSalesChannel(): void
    {
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        (new SalesChannelShippingMethodDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('shipping_method.salesChannels.id', $context->getSalesChannelId())], $criteria->getFilters());
    }
}
