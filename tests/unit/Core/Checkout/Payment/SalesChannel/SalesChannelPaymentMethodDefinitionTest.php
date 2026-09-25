<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\SalesChannel\SalesChannelPaymentMethodDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SalesChannelPaymentMethodDefinition::class)]
class SalesChannelPaymentMethodDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheSalesChannel(): void
    {
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        (new SalesChannelPaymentMethodDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('payment_method.salesChannels.id', $context->getSalesChannelId())], $criteria->getFilters());
    }
}
