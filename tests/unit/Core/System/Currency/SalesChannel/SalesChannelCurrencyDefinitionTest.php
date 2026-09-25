<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Currency\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\SalesChannel\SalesChannelCurrencyDefinition;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(SalesChannelCurrencyDefinition::class)]
class SalesChannelCurrencyDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheSalesChannel(): void
    {
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        (new SalesChannelCurrencyDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('currency.salesChannels.id', $context->getSalesChannelId())], $criteria->getFilters());
    }
}
